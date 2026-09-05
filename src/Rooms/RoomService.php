<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Rooms;

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\RoleSetBuilder;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\Discipline\FreezePolicy;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;

/**
 * Orchestrates rooms and the running game. Telegram processors call this
 * class; presenters render; delivery stays outside (SendPlan lists).
 */
final class RoomService
{
    private \Closure $random;

    public function __construct(
        private readonly RoomRepositoryContract $rooms,
        private readonly MafiaStateStoreContract $store,
        private readonly ProfileStoreContract $profiles,
        private readonly ClockContract $clock,
        private readonly MafiaSettings $settings = new MafiaSettings(),
        ?\Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    // ---- room lifecycle -------------------------------------------------

    public function createRoom(
        string $id,
        string $kind,
        ?string $chatId,
        string $title,
        string $hostUserId,
        string $hostName,
        int $minPlayers,
        int $maxPlayers,
        array $checkedRoles,
        string $locale,
        ?string $botId = null,
        ?MafiaSettings $settings = null,
    ): Room {
        $effective = $settings ?? $this->settings;
        $visibility = $kind === 'group' ? 'private' : 'public';
        $room = new Room(
            id: $id,
            kind: $kind,
            visibility: $visibility,
            status: 'lobby',
            title: $title,
            hostUserId: $hostUserId,
            chatId: $chatId,
            minPlayers: max(MafiaDefaults::PLAYERS_MIN, min(MafiaDefaults::PLAYERS_MAX, $minPlayers)),
            maxPlayers: max($minPlayers, min(min(MafiaDefaults::PLAYERS_MAX, $effective->playersMax), $maxPlayers)),
            checkedRoles: array_values(array_unique($checkedRoles)),
            locale: $locale,
            botId: $botId,
            nightSeconds: $effective->nightSeconds,
            discussionSeconds: $effective->discussionSeconds,
            voteSeconds: $effective->voteSeconds,
            shuffleSeats: $effective->shuffleSeats,
            createdAt: $this->clock->now(),
        );
        $this->rooms->save($room);
        $this->rooms->addMember($room->id, new Member($hostUserId, $hostName));

        return $room;
    }

    /** @return string|null lang key on refusal */
    public function join(string $roomId, string $userId, string $name): ?string
    {
        $room = $this->requireRoom($roomId);
        foreach ($this->rooms->members($roomId) as $m) {
            if ($m->userId === $userId && $m->state === Member::STATE_JOINED) {
                return null; // idempotent re-join
            }
            // GRP-7: kicked players lose access to this lobby
            if ($m->userId === $userId && $m->state === Member::STATE_KICKED) {
                return 'kick.rejoin_forbidden_toast';
            }
        }
        if ($reason = (new JoinGuard($this->store, $this->profiles, $this->clock))->check($room, count($this->activeMembers($roomId)), $userId)) {
            return $reason;
        }
        $this->rooms->addMember($roomId, new Member($userId, $name));

        return null;
    }

    public function leave(string $roomId, string $userId): void
    {
        if ($this->requireRoom($roomId)->status !== 'lobby') {
            return; // mid-game leavers go through replace-with-bot flow
        }
        $this->rooms->removeMember($roomId, $userId, Member::STATE_LEFT);
        $members = $this->activeMembers($roomId);
        if ($members === []) {
            $this->rooms->save($this->requireRoom($roomId)->with(status: 'cancelled'));

            return;
        }
        $room = $this->requireRoom($roomId);
        if ($room->hostUserId === $userId) {
            $this->rooms->setHost($roomId, $members[0]->userId);
        }
    }

    /** @return string|null refusal reason key */
    public function kick(string $roomId, string $actorId, string $targetId): ?string
    {
        $room = $this->requireRoom($roomId);
        if ($room->status !== 'lobby') {
            return 'kick.only_before_start_toast';
        }
        if ($room->hostUserId !== $actorId) {
            return 'kick.no_rights_toast';
        }
        if ($actorId === $targetId) {
            return 'kick.self_forbidden_toast';
        }
        $this->rooms->removeMember($roomId, $targetId, Member::STATE_KICKED);

        return null;
    }

    public function addBot(string $roomId, string $botUserId, string $botName): void
    {
        $this->rooms->addMember($roomId, new Member($botUserId, $botName, isBot: true));
    }

    /**
     * Start validation + snapshot creation. Roles are dealt here so callers
     * only announce.
     *
     * @return array{0:?GameSnapshot, 1:?string} snapshot or refusal reason key
     */
    public function start(string $roomId): array
    {
        $room = $this->requireRoom($roomId);
        if ($room->status !== 'lobby') {
            return [null, 'errors.game_in_progress_here'];
        }
        $members = $this->activeMembers($roomId);
        if (count($members) < $room->minPlayers) {
            return [null, 'lobby.not_enough_start_toast'];
        }
        $humans = array_filter($members, fn ($m) => ! $m->isBot);
        $confirmed = $this->store->dmConfirmations($roomId, array_map(fn ($m) => $m->userId, $humans));
        foreach ($confirmed as $userId => $ok) {
            if (! $ok) {
                return [null, 'interface.dm_required'];
            }
        }

        $build = (new RoleSetBuilder())->build(count($members), $room->checkedRoles);
        if (! $build->ok || count($build->roles) !== count($members)) {
            return [null, 'rooms.roles_invalid_toast'];
        }

        $order = $build->roles;
        $this->shuffle($order);

        $seated = array_values($members);
        if ($room->shuffleSeats) {
            $this->shuffle($seated);
        }

        $seats = [];
        foreach ($seated as $i => $member) {
            $role = $order[$i];
            $seats[] = new SeatState(
                seat: $i + 1,
                userId: $member->userId,
                name: $member->name,
                isBot: $member->isBot,
                role: $role,
                bullets: in_array($role, ['sniper', 'bandit'], true) ? 1 : 0,
                selfHealLeft: $role === 'doctor' ? 1 : 0,
                elderShield: $role === 'elder',
            );
        }

        $gameId = bin2hex(random_bytes(6));
        $snapshot = new GameSnapshot(
            gameId: $gameId,
            roomId: $roomId,
            chatId: $room->chatId,
            locale: $room->locale,
            phase: PhaseEnum::Night,
            phaseNumber: 1,
            dayNumber: 0,
            deadlineAt: $this->clock->now() + $room->nightSeconds,
            mirrorOn: $room->chatId !== null,
            seats: $seats,
            botId: $room->botId,
            nightSeconds: $room->nightSeconds,
            discussionSeconds: $room->discussionSeconds,
            voteSeconds: $room->voteSeconds,
        );
        $this->store->saveSnapshot($snapshot);
        $this->rooms->save($this->requireRoom($roomId)->with(status: 'running', lastGameId: $gameId));

        return [$snapshot, null];
    }

    public function finish(string $roomId, string $gameId, GameResultEnum $result): void
    {
        $room = $this->requireRoom($roomId);
        $this->rooms->save($room->with(status: 'finished', lastGameId: $gameId));
    }

    // ---- helpers ---------------------------------------------------------

    public function requireRoom(string $roomId): Room
    {
        return $this->findRoom($roomId) ?? throw new \RuntimeException("Unknown mafia room {$roomId}");
    }

    public function findRoom(string $roomId): ?Room
    {
        return $this->rooms->find($roomId);
    }

    /** @return list<Member> */
    public function activeMembers(string $roomId): array
    {
        return array_values(array_filter(
            $this->rooms->members($roomId),
            fn (Member $m) => $m->state === Member::STATE_JOINED
        ));
    }

    public function freezePolicy(): FreezePolicy
    {
        return new FreezePolicy($this->profiles, $this->clock);
    }

    private function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = ($this->random)($i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
}

<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia;

use BAGArt\TelegramBotMafia\Bots\NicknameFactory;
use BAGArt\TelegramBotMafia\Contracts\BotBrainContract;
use BAGArt\TelegramBotMafia\Contracts\ClockContract;
use BAGArt\TelegramBotMafia\Contracts\MafiaStateStoreContract;
use BAGArt\TelegramBotMafia\Contracts\ProfileStoreContract;
use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\GameSnapshot;
use BAGArt\TelegramBotMafia\Core\NightAction;
use BAGArt\TelegramBotMafia\Core\NightResolver;
use BAGArt\TelegramBotMafia\Core\PublicStateView;
use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\Core\RoleSetBuilder;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\Core\VoteTally;
use BAGArt\TelegramBotMafia\Core\WinConditionChecker;
use BAGArt\TelegramBotMafia\Discipline\FreezePolicy;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\I18n\LocaleResolver;
use BAGArt\TelegramBotMafia\Presentation\GameCardRenderer;
use BAGArt\TelegramBotMafia\Presentation\GroupPresenter;
use BAGArt\TelegramBotMafia\Presentation\InterfacePresenter;
use BAGArt\TelegramBotMafia\Presentation\Keyboards;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;
use BAGArt\TelegramBotMafia\Rooms\Room;
use BAGArt\TelegramBotMafia\Rooms\RoomService;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;
use BAGArt\TelegramBotMafia\Support\CallbackData;

/**
 * Orchestrates rooms + games and emits SendPlans. Delivery stays outside:
 * Telegram processors push plans through TgSenderContract. Pure-PHP/tests use
 * the static instance seam instead of a container.
 */
final class GameCoordinator
{
    private static ?self $instance = null;

    /** GRP-8: host phase extension length */
    public const EXTENSION_SECONDS = 30;

    /** @var array<string, LangPack> */
    private array $langs = [];

    private \Closure $random;

    public function __construct(
        private readonly RoomService $rooms,
        private readonly MafiaStateStoreContract $store,
        private readonly ProfileStoreContract $profiles,
        private readonly ClockContract $clock,
        private readonly string $langBasePath,
        private readonly BotBrainContract $brain,
        private readonly MafiaSettings $settings = new MafiaSettings(),
        ?\Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $max): int => random_int(0, $max);
    }

    public static function setInstance(?self $coordinator): void
    {
        self::$instance = $coordinator;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    // ---- rooms -------------------------------------------------------------

    public function createRoom(string $kind, ?string $chatId, string $title, string $hostId, string $hostName, int $min, int $max, array $checkedRoles, string $locale, ?string $botId = null, ?MafiaSettings $settings = null): Room
    {
        $room = $this->rooms->createRoom(
            id: bin2hex(random_bytes(6)),
            kind: $kind,
            chatId: $chatId,
            title: $title !== '' ? $title : ('Mafia #'.substr((string) $this->clock->now(), -5)),
            hostUserId: $hostId,
            hostName: $hostName,
            minPlayers: $min,
            maxPlayers: $max,
            checkedRoles: $checkedRoles,
            locale: $locale,
            botId: $botId,
            settings: $settings ?? $this->settings,
        );

        return $this->rooms->requireRoom($room->id);
    }

    public function join(string $roomId, string $userId, string $name): array
    {
        $reason = $this->rooms->join($roomId, $userId, $name);
        if ($reason !== null) {
            return ['toast' => $reason, 'plans' => []];
        }
        $room = $this->rooms->requireRoom($roomId);
        $count = count($this->rooms->activeMembers($roomId));
        $lang = $this->lang($room->locale);

        // GRP-2 DM gate: group joins are provisional until the player
        // confirms the bot's DM channel (start stays blocked otherwise)
        $confirmed = $this->store->dmConfirmations($roomId, [$userId])[$userId] ?? false;
        $dmCheck = $room->chatId !== null && ! $confirmed ? new SendPlan(
            $userId,
            $lang->t('lobby.ready_check_dm', ['chat' => $room->title], escape: false),
            Keyboards::single([
                ['label' => $lang->t('lobby.ready_check_button'), 'callback' => CallbackData::encode('ready', $room->id)],
            ]),
        ) : null;

        return [
            'toast' => 'lobby.joined_toast',
            'plans' => array_values(array_filter([
                new SendPlan($this->surface($room), $lang->t('lobby.joined_broadcast', [
                    'name' => $name, 'count' => $count, 'max' => $room->maxPlayers,
                ])),
                $dmCheck,
            ], fn (?SendPlan $p) => $p !== null)),
        ];
    }

    public function leave(string $roomId, string $userId): array
    {
        $room = $this->rooms->requireRoom($roomId);
        if ($room->status === 'running') {
            $replaced = $this->replaceWithBot($room, $userId);
            if ($replaced !== null) {
                return $replaced;
            }

            return ['toast' => null, 'plans' => []];
        }
        $name = $this->memberName($roomId, $userId);
        $this->rooms->leave($roomId, $userId);

        return [
            'toast' => 'rooms.left_toast',
            'plans' => [
                new SendPlan($this->surface($room), $this->lang($room->locale)->t('lobby.left_broadcast', [
                    'name' => $name, 'count' => count($this->rooms->activeMembers($roomId)), 'max' => $room->maxPlayers,
                ])),
            ],
        ];
    }

    /**
     * BOT-6: a mid-game leaver's seat is taken over by a fresh bot that
     * inherits exactly the seat's knowledge state — nothing more.
     *
     * @return array{toast: ?string, plans: list<SendPlan>}|null null when no
     *         running game seat belongs to the user (caller falls back)
     */
    private function replaceWithBot(Room $room, string $userId): ?array
    {
        $snapshot = $room->lastGameId !== null ? $this->store->loadSnapshot((string) $room->lastGameId) : null;
        $seat = $snapshot?->seatByUser($userId);
        if ($snapshot === null || $seat === null || ! $seat->alive || $seat->isBot) {
            return null;
        }
        $lang = $this->lang($snapshot->locale);
        $factory = new NicknameFactory($lang, $this->random);
        $botName = $factory->next();
        $seats = array_map(
            fn (SeatState $s) => $s->userId === $userId
                ? $s->with(userId: 'bot:'.bin2hex(random_bytes(4)), name: $botName, isBot: true)
                : $s,
            $snapshot->seats
        );
        $this->store->saveSnapshot($snapshot->with(seats: $seats));

        return [
            'toast' => null,
            'plans' => [new SendPlan(
                (string) ($snapshot->chatId ?? $room->hostUserId),
                $lang->t('kick.leaver_replaced', ['name' => $seat->name, 'bot' => $botName], escape: false)
            )],
        ];
    }

    public function kick(string $roomId, string $actorId, string $targetId): array
    {
        $reason = $this->rooms->kick($roomId, $actorId, $targetId);
        if ($reason !== null) {
            return ['toast' => $reason, 'plans' => []];
        }
        $room = $this->rooms->requireRoom($roomId);

        return [
            'toast' => 'kick.done',
            'plans' => [
                new SendPlan(
                    $this->surface($room),
                    $this->lang($room->locale)->t('kick.done', ['name' => $this->memberName($roomId, $targetId)])
                ),
                // GRP-7: the kicked player loses access and learns why
                new SendPlan(
                    $targetId,
                    $this->lang($room->locale)->t('kick.kicked_dm', ['title' => $room->title])
                ),
            ],
        ];
    }

    public function addBot(string $roomId, string $actorId): array
    {
        $room = $this->rooms->requireRoom($roomId);
        if ($room->status !== 'lobby') {
            return ['toast' => 'kick.only_before_start_toast', 'plans' => []];
        }
        if ($room->hostUserId !== $actorId) {
            return ['toast' => 'errors.only_host_adds_bots', 'plans' => []];
        }
        $members = $this->rooms->activeMembers($roomId);
        $bots = count(array_filter($members, fn ($m) => $m->isBot));
        if (count($members) >= $room->maxPlayers || $bots >= $this->settings->maxBots) {
            return ['toast' => 'errors.bots_limit_reached', 'plans' => []];
        }
        $factory = new NicknameFactory($this->lang($room->locale), $this->random);
        $botId = 'bot:'.bin2hex(random_bytes(4));
        $this->rooms->addBot($roomId, $botId, $factory->next());
        $room = $this->rooms->requireRoom($roomId);

        return [
            'toast' => null,
            'plans' => [$this->lobbyCard($room, $actorId)],
        ];
    }

    public function confirmDm(string $roomId, string $userId): void
    {
        $this->store->setDmConfirmed($roomId, $userId);
    }

    // ---- game lifecycle ----------------------------------------------------

    /** @return array{0: list<SendPlan>, 1: ?string} plans + refusal toast */
    public function start(string $roomId, ?string $actorId = null): array
    {
        if ($actorId !== null && $this->rooms->requireRoom($roomId)->hostUserId !== $actorId) {
            return [[], 'errors.only_host_action'];
        }
        [$snapshot, $reason] = $this->rooms->start($roomId);
        if ($snapshot === null) {
            return [[], $reason];
        }
        $this->autoActBots($snapshot);
        $this->store->saveSnapshot($snapshot);
        $lang = $this->lang($snapshot->locale);

        $plans = [];
        if ($snapshot->chatId !== null) {
            $group = new GroupPresenter($lang, $this->cardRenderer($lang));
            $plans = [...$plans, ...$group->phaseAnnounce($snapshot)];
        }
        $iface = new InterfacePresenter($lang, $this->cardRenderer($lang));
        foreach (InterfacePresenter::humanSeats($snapshot) as $seat) {
            $plans = [...$plans, ...$iface->roleDealt($snapshot, $seat->seat)];
        }
        $plans = [...$plans, ...$iface->phaseAnnounce($snapshot)];

        return [$plans, null];
    }

    /** @return array{0: list<SendPlan>, 1: ?string} */
    public function castNight(string $gameId, string $userId, ?int $targetSeat): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt !== null) {
            return [[], 'errors.wrong_phase_toast'];
        }
        if ($snapshot->phase !== PhaseEnum::Night) {
            return [[], 'errors.wrong_phase_toast'];
        }
        $me = $snapshot->seatByUser($userId);
        if ($me === null || ! $me->alive || $me->role === null) {
            return [[], 'errors.dead_no_actions_toast'];
        }
        if (! in_array(RoleCatalog::action($me->role), ['kill', 'heal', 'block_action', 'protect', 'check_alignment', 'check_exact_role'], true)) {
            return [[], 'errors.dead_no_actions_toast'];
        }
        foreach ($snapshot->nightActions as $a) {
            if ($a->actorSeat === $me->seat) {
                return [[], 'errors.double_action_toast'];
            }
        }
        if ($targetSeat !== null) {
            $target = $snapshot->seat($targetSeat);
            if ($target === null || ! $target->alive) {
                return [[], 'errors.stale_action_toast'];
            }
            if ($targetSeat === $me->seat && $me->role !== 'doctor') {
                return [[], 'errors.self_target_forbidden_toast'];
            }
            if ($targetSeat === $me->seat && $me->role === 'doctor' && $me->selfHealLeft <= 0) {
                return [[], 'errors.doctor_self_limit_toast'];
            }
        }

        $snapshot = $snapshot->with(nightActions: [
            ...$snapshot->nightActions,
            new NightAction($me->seat, RoleCatalog::action($me->role) ?? 'kill', $targetSeat),
        ]);

        return $this->afterNightSubmission($snapshot);
    }

    public function skipNight(string $gameId, string $userId): array
    {
        $snapshot = $this->requireGame($gameId);
        $me = $snapshot->seatByUser($userId);
        if ($me === null) {
            return [[], 'errors.not_participant_toast'];
        }

        return $this->castNightSkipAs($snapshot, $me->seat);
    }

    /** @return array{0: list<SendPlan>, 1: ?string} */
    public function castVote(string $gameId, string $userId, ?int $targetSeat): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt !== null || $snapshot->phase !== PhaseEnum::DayVoting) {
            return [[], 'errors.wrong_phase_toast'];
        }
        $me = $snapshot->seatByUser($userId);
        if ($me === null || ! $me->alive) {
            return [[], 'errors.dead_no_vote_toast'];
        }
        if (isset($snapshot->votes[$userId])) {
            return [[], 'errors.double_action_toast'];
        }
        if ($targetSeat !== null && $targetSeat === $me->seat) {
            return [[], 'errors.self_target_forbidden_toast'];
        }
        if ($targetSeat !== null && $snapshot->revoteCandidates !== []
            && ! in_array($targetSeat, $snapshot->revoteCandidates, true)) {
            return [[], 'errors.stale_action_toast'];
        }

        $snapshot = $snapshot->with(votes: [...$snapshot->votes, $userId => $targetSeat ?? -1]);
        $this->store->saveSnapshot($snapshot);

        $votersAlive = array_filter(
            InterfacePresenter::humanSeats($snapshot),
            fn (SeatState $s) => $s->alive
        );
        $botsAlive = array_filter($snapshot->aliveSeats(), fn (SeatState $s) => $s->isBot);
        $expected = count($snapshot->aliveSeats());
        if (count($snapshot->votes) >= $expected && count($votersAlive) + count($botsAlive) >= $expected) {
            return $this->closeVote($snapshot);
        }

        return [[], 'day.vote_cast_toast'];
    }

    /** Lazy deadline enforcement — call on any interaction with the game. */
    public function advanceIfOverdue(string $gameId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt !== null || $snapshot->phase === PhaseEnum::Ended) {
            return [];
        }
        if ($this->clock->now() < $snapshot->deadlineAt) {
            return [];
        }

        return match ($snapshot->phase) {
            PhaseEnum::Night => $this->resolveNight($snapshot)[0],
            PhaseEnum::DayDiscussion => $this->beginVoting($snapshot),
            PhaseEnum::DayVoting => $this->closeVote($snapshot)[0],
            default => [],
        };
    }

    public function pause(string $gameId, string $actorId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt !== null || $snapshot->phase === PhaseEnum::Ended) {
            return [[], null];
        }
        if (! $this->isHostOf($snapshot->roomId, $actorId)) {
            return [[], 'errors.only_host_action'];
        }
        $paused = $snapshot->with(pausedAt: $this->clock->now());
        $this->store->saveSnapshot($paused);
        $lang = $this->lang($snapshot->locale);
        $actor = $snapshot->seatByUser($actorId);
        $text = $lang->t('extras.paused_notice', ['name' => $actor?->name ?? $actorId], escape: false);
        if ($snapshot->chatId !== null) {
            return [[new SendPlan((string) $snapshot->chatId, $text)], null];
        }

        return [
            array_map(
                fn (SeatState $s) => new SendPlan((string) $s->userId, $text),
                InterfacePresenter::humanSeats($paused)
            ),
            null,
        ];
    }

    public function resume(string $gameId, string $actorId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt === null) {
            return [[], null];
        }
        if (! $this->isHostOf($snapshot->roomId, $actorId)) {
            return [[], 'errors.only_host_action'];
        }
        $shift = $this->clock->now() - $snapshot->pausedAt;
        $resumed = $snapshot->with(pausedAt: null, deadlineAt: $snapshot->deadlineAt + $shift);
        $this->store->saveSnapshot($resumed);
        $lang = $this->lang($snapshot->locale);
        $text = $lang->t('extras.resumed_toast', escape: false);
        if ($snapshot->chatId !== null) {
            return [[new SendPlan((string) $snapshot->chatId, $text)], null];
        }

        return [
            array_map(
                fn (SeatState $s) => new SendPlan((string) $s->userId, $text),
                InterfacePresenter::humanSeats($resumed)
            ),
            null,
        ];
    }

    /** GRP-8: host adds 30s to the current phase, once per phase. */
    public function extendPhase(string $gameId, string $actorId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->pausedAt !== null || $snapshot->phase === PhaseEnum::Ended) {
            return [[], 'errors.wrong_phase_toast'];
        }
        if (! $this->isHostOf($snapshot->roomId, $actorId)) {
            return [[], 'errors.only_host_action'];
        }
        if (in_array($snapshot->phaseNumber, $snapshot->extendedPhases, true)) {
            return [[], 'errors.extension_used_toast'];
        }
        $extended = $snapshot->with(
            deadlineAt: $snapshot->deadlineAt + self::EXTENSION_SECONDS,
            extendedPhases: [...$snapshot->extendedPhases, $snapshot->phaseNumber],
        );
        $this->store->saveSnapshot($extended);
        $lang = $this->lang($snapshot->locale);

        return [[], $lang->t('extras.extended_toast', ['sec' => self::EXTENSION_SECONDS])];
    }

    /** GRP-6: recreate the lobby with identical settings after a finished game. */
    public function rematch(string $finishedGameId, string $actorId): array
    {
        $snapshot = $this->store->loadSnapshot($finishedGameId);
        if ($snapshot === null || $snapshot->phase !== PhaseEnum::Ended) {
            return ['toast' => 'errors.stale_action_toast', 'plans' => [], 'roomId' => null];
        }
        $old = $this->rooms->requireRoom($snapshot->roomId);
        $actor = $snapshot->seatByUser($actorId);
        $room = $this->createRoom(
            kind: $old->kind,
            chatId: $old->chatId,
            title: $old->title,
            hostId: $actorId,
            hostName: $actor?->name ?? $actorId,
            min: $old->minPlayers,
            max: $old->maxPlayers,
            checkedRoles: $old->checkedRoles,
            locale: $old->locale,
            botId: $old->botId,
            settings: new MafiaSettings(
                nightSeconds: $old->nightSeconds,
                discussionSeconds: $old->discussionSeconds,
                voteSeconds: $old->voteSeconds,
                locale: $old->locale,
            ),
        );
        $lang = $this->lang($old->locale);
        $plans = [$this->lobbyCard($room, $actorId)];
        foreach ($snapshot->seats as $seat) {
            if (! $seat->isBot && $seat->userId !== $actorId) {
                $plans[] = new SendPlan(
                    (string) $seat->userId,
                    $lang->t('end.rematch_created'),
                    $this->lobbyCard($room, $seat->userId)->keyboard
                );
            }
        }

        return ['toast' => 'end.rematch_created', 'plans' => $plans, 'roomId' => $room->id];
    }

    /**
     * GRP-9: emergency assembly — an alive player drops the remaining
     * discussion and voting starts now. Budget: once per player, ≤2 per game;
     * night is uninterruptible.
     */
    public function emergencyAssembly(string $gameId, string $userId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->phase !== PhaseEnum::DayDiscussion || $snapshot->pausedAt !== null) {
            return [[], 'errors.wrong_phase_toast'];
        }
        $me = $snapshot->seatByUser($userId);
        if ($me === null || ! $me->alive) {
            return [[], 'errors.dead_no_actions_toast'];
        }
        if (in_array($userId, $snapshot->emergencyCalls, true)) {
            return [[], 'errors.emergency_used_toast'];
        }
        if (count($snapshot->emergencyCalls) >= 2) {
            return [[], 'errors.emergency_budget_toast'];
        }

        $called = $snapshot->with(emergencyCalls: [...$snapshot->emergencyCalls, $userId]);
        $this->store->saveSnapshot($called);

        return [$this->beginVoting($called), null];
    }

    /**
     * GRP-7: host ends the game early. First call returns a confirmation
     * card; the actual end happens on the 'endearlygo' callback.
     */
    public function endEarlyAsk(string $gameId, string $actorId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->phase === PhaseEnum::Ended) {
            return [[], null];
        }
        if (! $this->isHostOf($snapshot->roomId, $actorId)) {
            return [[], 'errors.only_host_action'];
        }
        $lang = $this->lang($snapshot->locale);

        return [[new SendPlan(
            (string) ($snapshot->chatId ?? $actorId),
            $lang->t('kick.end_early_ask'),
            Keyboards::single([
                ['label' => $lang->t('kick.end_early_confirm'), 'callback' => CallbackData::encode('endearlygo', $gameId), 'style' => 'danger'],
            ])
        )], null];
    }

    public function endEarlyGo(string $gameId, string $actorId): array
    {
        $snapshot = $this->requireGame($gameId);
        if ($snapshot->phase === PhaseEnum::Ended) {
            return [[], null];
        }
        if (! $this->isHostOf($snapshot->roomId, $actorId)) {
            return [[], 'errors.only_host_action'];
        }

        return [$this->doEndGame($snapshot->with(result: GameResultEnum::Cancelled), []), null];
    }

    private function isHostOf(string $roomId, string $actorId): bool
    {
        try {
            return $this->rooms->requireRoom($roomId)->hostUserId === $actorId;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * Group message → interface feeds while a game runs there. Returns fan-out
     * plans (author excluded).
     *
     * @return list<SendPlan>
     */
    public function mirrorGroupMessage(string $chatId, string $authorName, string $text): array
    {
        $snapshot = $this->store->gameByChat($chatId);
        if ($snapshot === null || ! $snapshot->mirrorOn || $snapshot->pausedAt !== null
            || $snapshot->phase === PhaseEnum::Ended) {
            return [];
        }
        $feed = $this->lang($snapshot->locale)->t('interface.feed_from_group', [
            'author' => $authorName, 'text' => $text,
        ], escape: false);
        $plans = [];
        foreach (InterfacePresenter::humanSeats($snapshot) as $seat) {
            if ($seat->name === $authorName) {
                continue;
            }
            $plans[] = new SendPlan((string) $seat->userId, $feed);
        }

        return $plans;
    }

    /** Interface player speech → all feeds (+ group for rooms born from chats). */
    public function relaySay(string $gameId, string $userId, string $text): array
    {
        $snapshot = $this->requireGame($gameId);
        $me = $snapshot->seatByUser($userId);
        if ($me === null || ! $me->alive) {
            return [];
        }
        $feed = $this->lang($snapshot->locale)->t('interface.feed_from_interface', [
            'author' => $me->name, 'text' => $text,
        ], escape: false);
        $plans = [];
        foreach (InterfacePresenter::humanSeats($snapshot) as $seat) {
            if ($seat->userId !== $userId) {
                $plans[] = new SendPlan((string) $seat->userId, $feed);
            }
        }
        if ($snapshot->chatId !== null) {
            $plans[] = new SendPlan((string) $snapshot->chatId, $feed);
        }

        return $plans;
    }

    // ---- internals ---------------------------------------------------------

    private function afterNightSubmission(GameSnapshot $snapshot): array
    {
        $pending = $this->pendingHumanActors($snapshot);
        $submitted = count(array_filter(
            $snapshot->nightActions,
            fn (NightAction $a) => in_array($a->actorSeat, $pending, true)
        ));
        $this->store->saveSnapshot($snapshot);
        if ($submitted >= count($pending)) {
            return $this->resolveNight($snapshot);
        }

        return [[], 'night.cast_toast'];
    }

    /** @return list<int> seats of living humans whose role must act tonight */
    private function pendingHumanActors(GameSnapshot $snapshot): array
    {
        $out = [];
        foreach ($snapshot->aliveSeats() as $seat) {
            if ($seat->isBot || $seat->role === null) {
                continue;
            }
            if (! in_array(RoleCatalog::action($seat->role), ['kill', 'heal', 'block_action', 'protect', 'check_alignment', 'check_exact_role'], true)) {
                continue;
            }
            $out[] = $seat->seat;
        }

        return $out;
    }

    private function autoActBots(GameSnapshot $snapshot): void
    {
        foreach ($snapshot->aliveSeats() as $seat) {
            if (! $seat->isBot || $seat->role === null) {
                continue;
            }
            $role = (string) $seat->role;
            if (! in_array(RoleCatalog::action($role), ['kill', 'heal', 'block_action', 'protect', 'check_alignment', 'check_exact_role'], true)) {
                continue;
            }
            $view = PublicStateView::fromSeats(
                $snapshot->seats,
                $snapshot->phase,
                $snapshot->dayNumber,
                $this->teammateSeatsOf($snapshot, $role)
            );
            $target = $this->brain->chooseNightTarget($view, $seat->seat, $role);
            $snapshot = $snapshot->with(nightActions: [
                ...$snapshot->nightActions,
                new NightAction($seat->seat, RoleCatalog::action($role) ?? 'kill', $target),
            ]);
        }
        $this->store->saveSnapshot($snapshot);
    }

    private function castNightSkipAs(GameSnapshot $snapshot, int $seat): array
    {
        if ($snapshot->pausedAt !== null) {
            return [[], 'errors.wrong_phase_toast'];
        }
        foreach ($snapshot->nightActions as $a) {
            if ($a->actorSeat === $seat) {
                return [[], 'errors.double_action_toast'];
            }
        }
        $snapshot = $snapshot->with(nightActions: [
            ...$snapshot->nightActions,
            new NightAction($seat, NightAction::SKIP, null),
        ]);

        return $this->afterNightSubmission($snapshot);
    }

    /** @return array{0: list<SendPlan>, 1: ?string} */
    private function resolveNight(GameSnapshot $snapshot): array
    {
        $report = (new NightResolver())->resolve($snapshot);
        $seats = array_map(function (SeatState $s) use ($report) {
            if (in_array($s->seat, $report->deaths, true)) {
                return $s->with(alive: false);
            }
            if (in_array($s->seat, $report->elderSaved, true)) {
                return $s->with(elderShield: false);
            }
            if ($s->role === 'doctor' && $report->savedSeat === $s->seat) {
                return $s->with(selfHealLeft: max(0, $s->selfHealLeft - 1));
            }

            return $s->with(tonightBlocked: false, tonightProtected: false, tonightHealed: false);
        }, $snapshot->seats);

        $snapshot = $snapshot->with(seats: $seats, nightActions: []);
        $win = (new WinConditionChecker())->evaluate($snapshot, $report->satanistSacrificed);
        $lang = $this->lang($snapshot->locale);

        $plans = [];
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->morning($snapshot, $report)];
        }
        $iface = new InterfacePresenter($lang, $this->cardRenderer($lang));
        $plans = [...$plans, ...$iface->morning($snapshot, $report)];

        if ($win !== null) {
            return [$this->doEndGame($snapshot->with(result: $win), $plans), null];
        }

        $snapshot = $snapshot->with(
            phase: PhaseEnum::DayDiscussion,
            phaseNumber: $snapshot->phaseNumber + 1,
            dayNumber: $snapshot->dayNumber + 1,
            deadlineAt: $this->clock->now() + $snapshot->discussionSeconds,
        );
        $this->store->saveSnapshot($snapshot);
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->phaseAnnounce($snapshot)];
        }
        $plans = [...$plans, ...$iface->phaseAnnounce($snapshot)];

        return [$plans, null];
    }

    private function beginVoting(GameSnapshot $snapshot): array
    {
        $snapshot = $snapshot->with(
            phase: PhaseEnum::DayVoting,
            deadlineAt: $this->clock->now() + $snapshot->voteSeconds,
        );
        // bots vote immediately
        foreach ($snapshot->aliveSeats() as $seat) {
            if (! $seat->isBot || isset($snapshot->votes[$seat->userId])) {
                continue;
            }
            $view = PublicStateView::fromSeats($snapshot->seats, $snapshot->phase, $snapshot->dayNumber);
            $choice = $this->brain->chooseVote($view, $seat->seat);
            $snapshot = $snapshot->with(votes: [...$snapshot->votes, $seat->userId => $choice ?? -1]);
        }
        $this->store->saveSnapshot($snapshot);
        $lang = $this->lang($snapshot->locale);

        $plans = [];
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->phaseAnnounce($snapshot)];
        }
        $iface = new InterfacePresenter($lang, $this->cardRenderer($lang));

        return [...$plans, ...$iface->phaseAnnounce($snapshot)];
    }

    /** @return array{0: list<SendPlan>, 1: ?string} */
    private function closeVote(GameSnapshot $snapshot): array
    {
        // sleepy discipline: alive humans who never voted are marked
        $seats = array_map(function (SeatState $s) use ($snapshot) {
            if ($s->isBot || ! $s->alive || isset($snapshot->votes[$s->userId])) {
                return $s;
            }
            $this->profiles->addSleepy($s->userId);

            return $s->with(missedVote: true);
        }, $snapshot->seats);
        $snapshot = $snapshot->with(seats: $seats);

        $outcome = VoteTally::tally($snapshot);
        $lang = $this->lang($snapshot->locale);
        $plans = [];
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->voteClosed($snapshot, $outcome)];
        }
        $plans = [...$plans, ...(new InterfacePresenter($lang, $this->cardRenderer($lang)))->voteClosed($snapshot, $outcome)];

        if ($outcome->eliminatedSeat !== null) {
            $seats = array_map(fn (SeatState $s) => $s->seat === $outcome->eliminatedSeat
                ? $s->with(alive: false)
                : $s, $snapshot->seats);
            $snapshot = $snapshot->with(seats: $seats);
            $win = (new WinConditionChecker())->evaluate($snapshot);
            if ($win !== null) {
                return [$this->doEndGame($snapshot->with(result: $win), $plans), null];
            }

            return [$this->nextNight($snapshot, $plans), null];
        }

        if ($outcome->requiresRevote()) {
            $snapshot = $snapshot->with(
                revoteCandidates: $outcome->tieCandidates,
                voteRound: $snapshot->voteRound + 1,
                votes: [],
                deadlineAt: $this->clock->now() + $snapshot->voteSeconds,
            );
            $this->store->saveSnapshot($snapshot);

            return [[...$plans, ...$this->beginVoting($snapshot)], null];
        }

        return [$this->nextNight($snapshot, $plans), null];
    }

    private function nextNight(GameSnapshot $snapshot, array $plans): array
    {
        // DISC-2: the sleepy badge sticks once earned and clears only when
        // the player actually votes again
        $reset = array_map(fn (SeatState $s) => $s->with(
            missedVote: ! isset($snapshot->votes[$s->userId]) && $s->missedVote,
            tonightBlocked: false,
            tonightProtected: false,
            tonightHealed: false,
        ), $snapshot->seats);
        $snapshot = $snapshot->with(
            seats: $reset,
            phase: PhaseEnum::Night,
            phaseNumber: $snapshot->phaseNumber + 1,
            votes: [],
            nightActions: [],
            revoteCandidates: [],
            voteRound: 0,
            deadlineAt: $this->clock->now() + $snapshot->nightSeconds,
        );
        $this->autoActBots($snapshot);
        $this->store->saveSnapshot($snapshot);
        $lang = $this->lang($snapshot->locale);
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->phaseAnnounce($snapshot)];
        }
        $iface = new InterfacePresenter($lang, $this->cardRenderer($lang));

        return [...$plans, ...$iface->phaseAnnounce($snapshot)];
    }

    /** @param  list<SendPlan>  $plans @return list<SendPlan> */
    private function doEndGame(GameSnapshot $snapshot, array $plans): array
    {
        $snapshot = $snapshot->with(phase: PhaseEnum::Ended, mirrorOn: false);
        $this->store->saveSnapshot($snapshot);
        $this->rooms->finish($snapshot->roomId, $snapshot->gameId, $snapshot->result ?? GameResultEnum::Cancelled);
        $lang = $this->lang($snapshot->locale);
        $iface = new InterfacePresenter($lang, $this->cardRenderer($lang));
        $plans = [...$plans, ...$iface->gameEnded($snapshot)];
        if ($snapshot->chatId !== null) {
            $plans = [...$plans, ...(new GroupPresenter($lang, $this->cardRenderer($lang)))->gameEnded($snapshot)];
        }
        $policy = new FreezePolicy($this->profiles, $this->clock);
        $interacted = array_fill_keys([...array_keys($snapshot->votes), ...array_map(
            fn (NightAction $a) => $snapshot->seat($a->actorSeat)?->userId ?? '',
            $snapshot->nightActions
        )], true);
        foreach (InterfacePresenter::humanSeats($snapshot) as $seat) {
            if ($seat->missedVote && ! isset($interacted[$seat->userId])) {
                $policy->registerSkip($seat->userId);
            } else {
                $policy->registerParticipation($seat->userId);
            }
        }

        return $plans;
    }

    // ---- helpers -----------------------------------------------------------

    private function requireGame(string $gameId): GameSnapshot
    {
        $snapshot = $this->store->loadSnapshot($gameId);
        if ($snapshot === null) {
            throw new \RuntimeException("Unknown mafia game {$gameId}");
        }

        return $snapshot;
    }

    /** @return list<int> */
    private function teammateSeatsOf(GameSnapshot $snapshot, string $viewerRole): array
    {
        if (! in_array($viewerRole, RoleCatalog::mafiaTeamIds(), true)) {
            return [];
        }
        $out = [];
        foreach ($snapshot->aliveSeats() as $s) {
            if (in_array((string) $s->role, RoleCatalog::mafiaTeamIds(), true)) {
                $out[] = $s->seat;
            }
        }

        return $out;
    }

    public function lang(string $locale): LangPack
    {
        return $this->langs[$locale] ??= new LangPack($locale, $this->langBasePath);
    }

    public function langPath(): string
    {
        return $this->langBasePath;
    }

    /** I18N-2 + ONB-1 chain: profile preference wins, then the game room's locale, else 'en'. */
    public function localeFor(string $userId): string
    {
        $snapshot = $this->store->gameByUser($userId);
        $room = $snapshot !== null ? $this->rooms()->findRoom($snapshot->roomId) : null;

        return (new LocaleResolver())->resolve(
            $this->profiles->preferredLocale($userId),
            $room?->locale,
            null,
            null,
        );
    }

    private function cardRenderer(LangPack $lang): GameCardRenderer
    {
        return new GameCardRenderer($lang);
    }

    public function lobbyCard(Room $room, ?string $viewerId = null): SendPlan
    {
        $lang = $this->lang($room->locale);
        $members = $this->rooms->activeMembers($room->id);
        $rows = [];
        foreach ($members as $i => $member) {
            $rows[] = $lang->t('lobby.player_row', [
                'seat' => $i + 1,
                'name' => ($member->isBot ? $lang->t('lobby.bot_marker').' ' : '').$member->name,
            ]);
        }
        $text = implode("\n", [
            sprintf($lang->t('lobby.card_header', escape: false).' (%d/%d)', count($members), $room->maxPlayers),
            ...$rows,
        ]);

        // joining happens through /play only — the card never offers a join
        // button; host controls render for the host viewer alone
        $isHost = $viewerId !== null && $viewerId === $room->hostUserId;
        $keyboard = [];
        if ($isHost) {
            $hostRow = [];
            if (count($members) < $room->maxPlayers) {
                $hostRow[] = ['label' => $lang->t('lobby.add_one_bot_button'), 'callback' => CallbackData::encode('addbot', $room->id)];
            }
            $hostRow[] = ['label' => $lang->t('rooms.host_start_button'), 'callback' => CallbackData::encode('begingame', $room->id), 'style' => 'success'];
            $keyboard[] = $hostRow;
            foreach ($members as $member) {
                if ($member->isBot || $member->userId === $room->hostUserId) {
                    continue;
                }
                $keyboard[] = [[
                    'label' => $lang->t('lobby.kick_button').' '.$member->name,
                    'callback' => CallbackData::encode('kick', $room->id, $member->userId),
                    'style' => 'danger',
                ]];
            }
        }
        $keyboard[] = [
            ['label' => $lang->t('rooms.leave_button'), 'callback' => CallbackData::encode('leave', $room->id)],
        ];

        return new SendPlan($this->surface($room), $text, $keyboard);
    }

    private function surface(Room $room): string
    {
        return (string) ($room->chatId ?? $room->hostUserId);
    }

    private function memberName(string $roomId, string $userId): string
    {
        foreach ($this->rooms->activeMembers($roomId) as $member) {
            if ($member->userId === $userId) {
                return $member->name;
            }
        }

        return $userId;
    }

    public function rooms(): RoomService
    {
        return $this->rooms;
    }

    public function store(): MafiaStateStoreContract
    {
        return $this->store;
    }

    public function profiles(): ProfileStoreContract
    {
        return $this->profiles;
    }

    public function roleSetBuilder(): RoleSetBuilder
    {
        return new RoleSetBuilder();
    }
}

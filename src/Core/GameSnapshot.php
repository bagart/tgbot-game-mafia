<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Core;

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;

/**
 * Full active-game truth as a readonly DTO. Only this crosses the Redis
 * boundary (versioned JSON); behavior never does.
 *
 * @phpstan-type SeatArray array{seat:int,userId:string,name:string,isBot:bool,role:?string,alive:bool,bullets:int,selfHealLeft:int,elderShield:bool,missedVote:bool,tb:bool,tp:bool,th:bool}
 */
final readonly class GameSnapshot
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  list<SeatState>  $seats
     * @param  list<NightAction>  $nightActions
     * @param  array<string, int>  $votes  voterUserId => target seat (-1 = abstain)
     * @param  list<int>  $revoteCandidates  empty = open vote among all alive
     */
    public function __construct(
        public string $gameId,
        public string $roomId,
        public ?string $chatId,
        public string $locale,
        public PhaseEnum $phase,
        public int $phaseNumber,
        public int $dayNumber,
        public int $deadlineAt,
        public bool $mirrorOn,
        public array $seats,
        public array $nightActions = [],
        public array $votes = [],
        public array $revoteCandidates = [],
        public int $voteRound = 0,
        public ?GameResultEnum $result = null,
        /** epoch when host paused the game; null = running */
        public ?int $pausedAt = null,
        /** owning bot (platform multi-bot); null = legacy/unknown */
        public ?string $botId = null,
        public int $nightSeconds = MafiaDefaults::NIGHT_SECONDS,
        public int $discussionSeconds = MafiaDefaults::DISCUSSION_SECONDS,
        public int $voteSeconds = MafiaDefaults::VOTE_SECONDS,
        /** phaseNumbers where the host already spent the +30s extension */
        public array $extendedPhases = [],
        /** GRP-9: userIds that already called an emergency assembly this game */
        public array $emergencyCalls = [],
    ) {
    }

    public function seat(int $n): ?SeatState
    {
        foreach ($this->seats as $s) {
            if ($s->seat === $n) {
                return $s;
            }
        }

        return null;
    }

    /** @return list<SeatState> */
    public function aliveSeats(): array
    {
        return array_values(array_filter($this->seats, fn ($s) => $s->alive));
    }

    public function seatByUser(string $userId): ?SeatState
    {
        foreach ($this->seats as $s) {
            if ($s->userId === $userId) {
                return $s;
            }
        }

        return null;
    }

    public function with(...$props): self
    {
        return new self(
            gameId: $props['gameId'] ?? $this->gameId,
            roomId: $props['roomId'] ?? $this->roomId,
            chatId: array_key_exists('chatId', $props) ? $props['chatId'] : $this->chatId,
            locale: $props['locale'] ?? $this->locale,
            phase: $props['phase'] ?? $this->phase,
            phaseNumber: $props['phaseNumber'] ?? $this->phaseNumber,
            dayNumber: $props['dayNumber'] ?? $this->dayNumber,
            deadlineAt: $props['deadlineAt'] ?? $this->deadlineAt,
            mirrorOn: $props['mirrorOn'] ?? $this->mirrorOn,
            seats: $props['seats'] ?? $this->seats,
            nightActions: $props['nightActions'] ?? $this->nightActions,
            votes: $props['votes'] ?? $this->votes,
            revoteCandidates: $props['revoteCandidates'] ?? $this->revoteCandidates,
            voteRound: $props['voteRound'] ?? $this->voteRound,
            result: array_key_exists('result', $props) ? $props['result'] : $this->result,
            pausedAt: array_key_exists('pausedAt', $props) ? $props['pausedAt'] : $this->pausedAt,
            botId: array_key_exists('botId', $props) ? $props['botId'] : $this->botId,
            nightSeconds: $props['nightSeconds'] ?? $this->nightSeconds,
            discussionSeconds: $props['discussionSeconds'] ?? $this->discussionSeconds,
            voteSeconds: $props['voteSeconds'] ?? $this->voteSeconds,
            extendedPhases: $props['extendedPhases'] ?? $this->extendedPhases,
            emergencyCalls: $props['emergencyCalls'] ?? $this->emergencyCalls,
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'v' => self::SCHEMA_VERSION,
            'gameId' => $this->gameId,
            'roomId' => $this->roomId,
            'chatId' => $this->chatId,
            'locale' => $this->locale,
            'phase' => $this->phase->value,
            'phaseNumber' => $this->phaseNumber,
            'dayNumber' => $this->dayNumber,
            'deadlineAt' => $this->deadlineAt,
            'mirrorOn' => $this->mirrorOn,
            'seats' => array_map(self::seatToArray(...), $this->seats),
            'nightActions' => array_map(
                fn (NightAction $a) => ['a' => $a->actorSeat, 't' => $a->type, 'g' => $a->targetSeat],
                $this->nightActions
            ),
            'votes' => $this->votes,
            'revoteCandidates' => $this->revoteCandidates,
            'voteRound' => $this->voteRound,
            'result' => $this->result?->value,
            'pausedAt' => $this->pausedAt,
            'botId' => $this->botId,
            'nightSeconds' => $this->nightSeconds,
            'discussionSeconds' => $this->discussionSeconds,
            'voteSeconds' => $this->voteSeconds,
            'extendedPhases' => $this->extendedPhases,
            'emergencyCalls' => $this->emergencyCalls,
        ], JSON_THROW_ON_ERROR);
    }

    /** Versioned entry point; v1 is the only schema so far. */
    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $version = (int) ($d['v'] ?? 0);
        if ($version !== self::SCHEMA_VERSION) {
            throw new \RuntimeException("Unsupported mafia snapshot version {$version}");
        }

        return new self(
            gameId: (string) $d['gameId'],
            roomId: (string) $d['roomId'],
            chatId: $d['chatId'] !== null ? (string) $d['chatId'] : null,
            locale: (string) $d['locale'],
            phase: PhaseEnum::from((string) $d['phase']),
            phaseNumber: (int) $d['phaseNumber'],
            dayNumber: (int) $d['dayNumber'],
            deadlineAt: (int) $d['deadlineAt'],
            mirrorOn: (bool) $d['mirrorOn'],
            seats: array_map(self::seatFromArray(...), $d['seats']),
            nightActions: array_map(
                fn (array $a) => new NightAction((int) $a['a'], (string) $a['t'], $a['g'] !== null ? (int) $a['g'] : null),
                $d['nightActions']
            ),
            votes: array_map(intval(...), (array) $d['votes']),
            revoteCandidates: array_map(intval(...), (array) $d['revoteCandidates']),
            voteRound: (int) ($d['voteRound'] ?? 0),
            result: $d['result'] !== null ? GameResultEnum::from((string) $d['result']) : null,
            pausedAt: isset($d['pausedAt']) && $d['pausedAt'] !== null ? (int) $d['pausedAt'] : null,
            botId: isset($d['botId']) && $d['botId'] !== null ? (string) $d['botId'] : null,
            nightSeconds: (int) ($d['nightSeconds'] ?? MafiaDefaults::NIGHT_SECONDS),
            discussionSeconds: (int) ($d['discussionSeconds'] ?? MafiaDefaults::DISCUSSION_SECONDS),
            voteSeconds: (int) ($d['voteSeconds'] ?? MafiaDefaults::VOTE_SECONDS),
            extendedPhases: array_map(intval(...), (array) ($d['extendedPhases'] ?? [])),
            emergencyCalls: array_map(strval(...), (array) ($d['emergencyCalls'] ?? [])),
        );
    }

    /** @return SeatArray */
    private static function seatToArray(SeatState $s): array
    {
        return [
            'seat' => $s->seat, 'userId' => $s->userId, 'name' => $s->name,
            'isBot' => $s->isBot, 'role' => $s->role, 'alive' => $s->alive,
            'bullets' => $s->bullets, 'selfHealLeft' => $s->selfHealLeft,
            'elderShield' => $s->elderShield, 'missedVote' => $s->missedVote,
            'tb' => $s->tonightBlocked, 'tp' => $s->tonightProtected, 'th' => $s->tonightHealed,
        ];
    }

    /** @param SeatArray $a */
    private static function seatFromArray(array $a): SeatState
    {
        return new SeatState(
            seat: (int) $a['seat'],
            userId: (string) $a['userId'],
            name: (string) $a['name'],
            isBot: (bool) $a['isBot'],
            role: $a['role'] !== null ? (string) $a['role'] : null,
            alive: (bool) $a['alive'],
            bullets: (int) $a['bullets'],
            selfHealLeft: (int) $a['selfHealLeft'],
            elderShield: (bool) $a['elderShield'],
            missedVote: (bool) $a['missedVote'],
            tonightBlocked: (bool) $a['tb'],
            tonightProtected: (bool) $a['tp'],
            tonightHealed: (bool) $a['th'],
        );
    }
}

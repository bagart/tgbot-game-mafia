<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Auth;

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotAccess\AccessRequest;
use BAGArt\TelegramBotManagement\Services\TelegramIdentityService;
use Illuminate\Support\Facades\DB;

/**
 * T2 "game.initiate" gate (D14/D15): starting a game in a chat needs the
 * platform-grantable capability in that chat's scope (deny-by-default).
 *
 * Legacy fallback (decided together with the antispam reason gate): group
 * initiation was open to every member before this gate, so subjects the
 * platform has no identity link for keep the legacy open behavior. Only a
 * provisioned subject (telegram id -> platform user) is decided, and that
 * decision fails closed when the access layer errors.
 */
final class T2Gate
{
    public const string CAPABILITY = 'game.initiate';

    /** @var \Closure(int): ?int  Telegram chat id -> owning workspace id. */
    private readonly \Closure $workspaceIdOf;

    /** @var (\Closure(int): ?int)|null  Identity seam; wins over $identities. */
    private readonly ?\Closure $platformUserIdOf;

    /**
     * @param  (\Closure(int): ?int)|null  $platformUserIdOf  Overrides identity
     *        resolution (tests / non-Laravel hosts); wins over $identities.
     * @param  (\Closure(int): ?int)|null  $workspaceIdOf  Overrides the default
     *        tg_entities chat -> workspace lookup.
     */
    public function __construct(
        private readonly AccessControlContract $access,
        private readonly ?TelegramIdentityService $identities = null,
        ?\Closure $platformUserIdOf = null,
        ?\Closure $workspaceIdOf = null,
    ) {
        $this->platformUserIdOf = $platformUserIdOf;
        $this->workspaceIdOf = $workspaceIdOf ?? self::workspaceResolver();
    }

    public function canInitiate(string $botId, int $chatId, int $tgUserId): bool
    {
        $platformUserId = $this->platformUserId($tgUserId);
        if ($platformUserId === null) {
            return true;
        }

        try {
            return $this->access->decide(new AccessRequest(
                botId: $botId,
                subjectId: (string) $platformUserId,
                capability: self::CAPABILITY,
                chatId: $chatId,
                workspaceId: ($this->workspaceIdOf)($chatId),
            ))->allowed;
        } catch (\Throwable) {
            return false;
        }
    }

    private function platformUserId(int $tgUserId): ?int
    {
        if ($this->platformUserIdOf !== null) {
            try {
                return ($this->platformUserIdOf)($tgUserId);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($this->identities === null) {
            return null;
        }

        try {
            return $this->identities->resolveByTelegramId($tgUserId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Workspace owning a chat via the tg_entities link (D2), non-bot entities
     * only — a bot row is an ownership fact, not a chat. Null when the
     * platform table is unavailable: chat-scoped grants still apply,
     * workspace-scope grants are simply not loaded (deny-by-default).
     */
    private static function workspaceResolver(): \Closure
    {
        return static function (int $chatId): ?int {
            try {
                $workspaceId = DB::table('tg_entities')
                    ->where('kind', '!=', 'bot')
                    ->where('external_id', $chatId)
                    ->orderBy('id')
                    ->value('workspace_id');
            } catch (\Throwable) {
                return null;
            }

            return $workspaceId === null ? null : (int) $workspaceId;
        };
    }
}

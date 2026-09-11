<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

/**
 * Tracks sent Telegram message IDs per game phase so presenters can
 * edit-in-place instead of sending new messages each phase transition.
 *
 * Key design: one message per (gameId, phase, chatId). Group phase
 * transitions (Night → DayDiscussion → DayVoting) reuse the same message
 * via editMessageText. New messages are only sent for DMs and final state.
 */
interface MessageTrackerContract
{
    /** Store a message ID for a game phase + chat. */
    public function track(string $gameId, string $phase, string $chatId, int $messageId): void;

    /** Get the last sent message ID for a phase + chat, or null if none. */
    public function lastMessage(string $gameId, string $phase, string $chatId): ?int;

    /** Delete all tracked messages for a game (cleanup on game end). */
    public function clear(string $gameId): void;
}

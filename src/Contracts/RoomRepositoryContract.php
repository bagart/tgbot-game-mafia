<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Contracts;

use BAGArt\TelegramBotMafia\Rooms\Member;
use BAGArt\TelegramBotMafia\Rooms\Room;

/**
 * Room + membership persistence. Rooms outlive games (lobby → finished),
 * membership drives joining/kicking and the start guard.
 */
interface RoomRepositoryContract
{
    public function save(Room $room): void;

    public function find(string $roomId): ?Room;

    public function findByChat(string $chatId, ?string $status = null): ?Room;

    /** @param  list<string>  $statuses */
    public function openRooms(?string $forUserId = null, array $statuses = ['lobby', 'running']): array;

    public function delete(string $roomId): void;

    public function addMember(string $roomId, Member $member): void;

    public function removeMember(string $roomId, string $userId, string $state): void;

    /** @return list<Member> */
    public function members(string $roomId): array;

    public function updateMember(Member $member): void;

    public function setHost(string $roomId, string $userId): void;
}

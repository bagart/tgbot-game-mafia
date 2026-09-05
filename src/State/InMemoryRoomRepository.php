<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Rooms\Member;
use BAGArt\TelegramBotMafia\Rooms\Room;

/**
 * Process-memory room repository (tests / pure-PHP). Production swaps the
 * Eloquent implementation of the same contract.
 */
final class InMemoryRoomRepository implements RoomRepositoryContract
{
    /** @var array<string, Room> */
    private array $rooms = [];

    /** @var array<string, array<string, Member>> roomId => userId => member */
    private array $members = [];

    public function save(Room $room): void
    {
        $this->rooms[$room->id] = $room;
        $this->members[$room->id] ??= [];
    }

    public function find(string $roomId): ?Room
    {
        return $this->rooms[$roomId] ?? null;
    }

    public function findByChat(string $chatId, ?string $status = null): ?Room
    {
        foreach ($this->rooms as $room) {
            if ($room->chatId === $chatId && ($status === null || $room->status === $status)) {
                return $room;
            }
        }

        return null;
    }

    public function openRooms(?string $forUserId = null, array $statuses = ['lobby', 'running']): array
    {
        $out = [];
        foreach ($this->rooms as $room) {
            if (! in_array($room->status, $statuses, true)) {
                continue;
            }
            $isMember = isset($this->members[$room->id][$forUserId ?? '']);
            if ($room->visibility === 'public' || $isMember) {
                $out[] = $room;
            }
        }

        return $out;
    }

    public function delete(string $roomId): void
    {
        unset($this->rooms[$roomId], $this->members[$roomId]);
    }

    public function addMember(string $roomId, Member $member): void
    {
        // re-join replaces prior state; fresh members append
        $this->members[$roomId][$member->userId] = new Member(
            $member->userId,
            $member->name,
            $member->isBot,
            Member::STATE_JOINED,
        );
    }

    public function removeMember(string $roomId, string $userId, string $state): void
    {
        if (isset($this->members[$roomId][$userId])) {
            $m = $this->members[$roomId][$userId];
            $this->members[$roomId][$userId] = new Member($m->userId, $m->name, $m->isBot, $state);
        }
    }

    public function members(string $roomId): array
    {
        return array_values($this->members[$roomId] ?? []);
    }

    public function updateMember(Member $member): void
    {
        foreach ($this->members as $roomId => $list) {
            if (isset($list[$member->userId])) {
                $this->members[$roomId][$member->userId] = $member;
            }
        }
    }

    public function setHost(string $roomId, string $userId): void
    {
        if (isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = $this->rooms[$roomId]->with(hostUserId: $userId);
        }
    }
}

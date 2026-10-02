<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\State;

use BAGArt\TelegramBotMafia\Contracts\RoomRepositoryContract;
use BAGArt\TelegramBotMafia\Rooms\Member;
use BAGArt\TelegramBotMafia\Rooms\Room;
use Illuminate\Database\ConnectionInterface;

/**
 * Eloquent-backed room repository. Replaces InMemoryRoomRepository for
 * production — rooms and members persist across restarts.
 */
final class EloquentRoomRepository implements RoomRepositoryContract
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $roomsTable = 'mafia_rooms',
        private readonly string $membersTable = 'mafia_room_members',
    ) {
    }

    public function save(Room $room): void
    {
        $this->connection->table($this->roomsTable)->upsert(
            [
                'id' => $room->id,
                'kind' => $room->kind,
                'visibility' => $room->visibility,
                'status' => $room->status,
                'title' => $room->title,
                'host_user_id' => $room->hostUserId,
                'chat_id' => $room->chatId,
                'bot_id' => $room->botId,
                'night_seconds' => $room->nightSeconds,
                'discussion_seconds' => $room->discussionSeconds,
                'vote_seconds' => $room->voteSeconds,
                'min_players' => $room->minPlayers,
                'max_players' => $room->maxPlayers,
                'role_config' => $room->checkedRoles !== [] ? $room->checkedRoles : null,
                'locale' => $room->locale,
                'last_game_id' => $room->lastGameId,
                'created_at' => $room->createdAt > 0 ? date('Y-m-d H:i:s', $room->createdAt) : now(),
                'updated_at' => now(),
            ],
            ['id'],
            [
                'kind', 'visibility', 'status', 'title', 'host_user_id',
                'chat_id', 'bot_id', 'night_seconds', 'discussion_seconds',
                'vote_seconds', 'min_players', 'max_players', 'role_config',
                'locale', 'last_game_id', 'updated_at',
            ],
        );
    }

    public function find(string $roomId): ?Room
    {
        $row = $this->connection->table($this->roomsTable)->where('id', $roomId)->first();

        return $row !== null ? $this->toRoom($row) : null;
    }

    public function findByChat(string $chatId, ?string $status = null): ?Room
    {
        $query = $this->connection->table($this->roomsTable)->where('chat_id', $chatId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $row = $query->first();

        return $row !== null ? $this->toRoom($row) : null;
    }

    public function openRooms(?string $forUserId = null, array $statuses = ['lobby', 'running']): array
    {
        $query = $this->connection->table($this->roomsTable)
            ->whereIn('status', $statuses);

        if ($forUserId !== null) {
            // Public rooms + rooms where user is a member.
            $memberRoomIds = $this->connection->table($this->membersTable)
                ->where('user_id', $forUserId)
                ->where('state', Member::STATE_JOINED)
                ->pluck('room_id')
                ->all();

            $query->where(function ($q) use ($memberRoomIds) {
                $q->where('visibility', 'public')
                    ->orWhereIn('id', $memberRoomIds);
            });
        } else {
            $query->where('visibility', 'public');
        }

        return array_map(
            [$this, 'toRoom'],
            $query->get()->all(),
        );
    }

    public function delete(string $roomId): void
    {
        $this->connection->table($this->roomsTable)->where('id', $roomId)->delete();
    }

    public function addMember(string $roomId, Member $member): void
    {
        $this->connection->table($this->membersTable)->updateOrInsert(
            ['room_id' => $roomId, 'user_id' => $member->userId],
            [
                'name' => $member->name,
                'is_bot' => $member->isBot,
                'state' => Member::STATE_JOINED,
                'updated_at' => now(),
            ],
        );
    }

    public function removeMember(string $roomId, string $userId, string $state): void
    {
        $this->connection->table($this->membersTable)
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->update(['state' => $state, 'updated_at' => now()]);
    }

    public function members(string $roomId): array
    {
        $rows = $this->connection->table($this->membersTable)
            ->where('room_id', $roomId)
            ->get()
            ->all();

        return array_map(
            fn (object $row) => new Member(
                userId: (string) $row->user_id,
                name: (string) $row->name,
                isBot: (bool) $row->is_bot,
                state: (string) $row->state,
            ),
            $rows,
        );
    }

    public function updateMember(Member $member): void
    {
        $this->connection->table($this->membersTable)
            ->where('user_id', $member->userId)
            ->update([
                'name' => $member->name,
                'is_bot' => $member->isBot,
                'state' => $member->state,
                'updated_at' => now(),
            ]);
    }

    public function setHost(string $roomId, string $userId): void
    {
        $this->connection->table($this->roomsTable)
            ->where('id', $roomId)
            ->update(['host_user_id' => $userId, 'updated_at' => now()]);
    }

    private function toRoom(object $row): Room
    {
        $roleConfig = $row->role_config !== null
            ? json_decode((string) $row->role_config, true, 512, JSON_THROW_ON_ERROR)
            : [];

        return new Room(
            id: (string) $row->id,
            kind: (string) $row->kind,
            visibility: (string) $row->visibility,
            status: (string) $row->status,
            title: (string) $row->title,
            hostUserId: (string) $row->host_user_id,
            chatId: $row->chat_id !== null ? (string) $row->chat_id : null,
            minPlayers: (int) $row->min_players,
            maxPlayers: (int) $row->max_players,
            checkedRoles: is_array($roleConfig) ? $roleConfig : [],
            locale: (string) $row->locale,
            botId: $row->bot_id !== null ? (string) $row->bot_id : null,
            nightSeconds: (int) ($row->night_seconds ?? 75),
            discussionSeconds: (int) ($row->discussion_seconds ?? 150),
            voteSeconds: (int) ($row->vote_seconds ?? 45),
            lastGameId: $row->last_game_id !== null ? (string) $row->last_game_id : null,
            createdAt: isset($row->created_at) ? strtotime((string) $row->created_at) : 0,
        );
    }
}

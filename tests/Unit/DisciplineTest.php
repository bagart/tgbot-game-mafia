<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Config\MafiaDefaults;
use BAGArt\TelegramBotMafia\Core\Enums\GameResultEnum;
use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Discipline\FreezePolicy;
use BAGArt\TelegramBotMafia\Presentation\GameCardRenderer;
use BAGArt\TelegramBotMafia\Presentation\GroupPresenter;
use BAGArt\TelegramBotMafia\Settings\MafiaSettings;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function hostGameIn(string $title): array
{
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, $title, 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    [, $reason] = $c->start($room->id);
    expect($reason)->toBeNull();
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    return [$c, $gameId];
}

it('freezes chronic skippers and blocks joining until the freeze expires (DISC-1)', function () {
    $c = CoordinatorFactory::make();
    $policy = new FreezePolicy($c->profiles(), CoordinatorFactory::$clock);

    // one skip: strike recorded, no freeze yet
    expect($policy->registerSkip('afk'))->toBeNull()
        ->and($c->profiles()->skips('afk'))->toBe(1)
        ->and($policy->isFrozen('afk'))->toBeFalse();

    // full participation resets the strike counter
    $policy->registerParticipation('afk');
    expect($c->profiles()->skips('afk'))->toBe(0);

    // two consecutive skips → frozen for FREEZE_MINUTES
    $policy->registerSkip('afk');
    $until = $policy->registerSkip('afk');
    expect($until)->toBe(CoordinatorFactory::$clock->now() + MafiaDefaults::FREEZE_MINUTES * 60)
        ->and($policy->isFrozen('afk'))->toBeTrue()
        ->and($c->profiles()->skips('afk'))->toBe(0);

    // join is refused while frozen…
    $room = $c->createRoom('group', '-1', 'L', 'h9', 'H9', 5, 8, [], 'en');
    expect($c->join($room->id, 'afk', 'Afk')['toast'])->toBe('rooms.join_frozen_toast');

    // …and allowed again once the freeze expires
    CoordinatorFactory::$clock->advance(MafiaDefaults::FREEZE_MINUTES * 60 + 1);
    expect($policy->isFrozen('afk'))->toBeFalse()
        ->and($c->join($room->id, 'afk', 'Afk')['toast'])->toBe('lobby.joined_toast');

    // persistence: profile state survives a process restart
    $restarted = CoordinatorFactory::restartFrom($c);
    expect($restarted->profiles()->sleepyTotal('afk'))->toBe(0);
});

it('badges sleepy voters at close and totals the stat (DISC-2)', function () {
    [$c, $gameId] = hostGameIn('Sleepy');

    // jump straight into day discussion so nobody can die overnight, then
    // let both phases time out: closeVote marks every alive human who
    // never voted
    $snap = $c->store()->loadSnapshot($gameId);
    $c->store()->saveSnapshot($snap->with(
        phase: PhaseEnum::DayDiscussion,
        deadlineAt: CoordinatorFactory::$clock->now() + 10,
    ));
    CoordinatorFactory::$clock->advance(11);
    $c->advanceIfOverdue($gameId);
    $voting = $c->store()->loadSnapshot($gameId);
    expect($voting?->phase)->toBe(PhaseEnum::DayVoting);

    CoordinatorFactory::$clock->advance(MafiaSettings::VOTE_MAX + 5);
    $c->advanceIfOverdue($gameId);
    $closed = $c->store()->loadSnapshot($gameId);
    expect($closed?->seatByUser('host1')?->missedVote)->toBeTrue()
        ->and($c->profiles()->sleepyTotal('host1'))->toBe(1);

    // badge shows on the player's card while they are alive
    $renderer = new GameCardRenderer($c->lang('en'));
    $seatNumber = $closed?->seatByUser('host1')?->seat;
    $revived = $closed?->with(seats: array_map(
        fn ($s) => $s->userId === 'host1' ? $s->with(alive: true) : $s,
        $closed?->seats ?? []
    ));
    expect($revived)->not->toBeNull()
        ->and($renderer->render($revived, $seatNumber))->toContain('😴');
});

it('toasts start-guard refusals with reasons (GRP-3)', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('interface', null, 'G', 'host1', 'Host', 5, 5, [], 'en');

    // below capacity
    [, $reason] = $c->start($room->id);
    expect($reason)->toBe('lobby.not_enough_start_toast');

    // joined but DM-unconfirmed human blocks the start
    $c->join($room->id, 'u2', 'Bob');
    for ($i = 0; $i < 3; $i++) {
        $c->addBot($room->id, 'host1');
    }
    [, $reason] = $c->start($room->id);
    expect($reason)->toBe('interface.dm_required');
});

it('renders live tally bars proportional to votes (GRP-5)', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('group', '-100', 'Tally', 'host1', 'Host', 5, 5, [], 'en');
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->start($room->id);
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    $snap = $c->store()->loadSnapshot($gameId)->with(
        phase: PhaseEnum::DayVoting,
        votes: ['voter1' => 3, 'voter2' => 3, 'voter3' => 2],
        deadlineAt: CoordinatorFactory::$clock->now() + 60,
    );
    $c->store()->saveSnapshot($snap);

    $presenter = new GroupPresenter($c->lang('en'), new BAGArt\TelegramBotMafia\Presentation\GameCardRenderer($c->lang('en')));
    $plans = $presenter->phaseAnnounce($snap);
    expect(count($plans))->toBe(1);
    $text = $plans[0]->text;
    expect($text)->toContain('██')
        ->toContain('█░');
});

it('dms kicked players and ends games early behind confirmation (GRP-7)', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('group', '-100', 'Kicks', 'host1', 'Host', 5, 8, [], 'en');
    $c->join($room->id, 'u2', 'Bob');

    // kick: broadcast + DM to the target
    $result = $c->kick($room->id, 'host1', 'u2');
    $targets = array_map(fn ($p) => (string) $p->chatId, $result['plans']);
    expect($targets)->toContain('u2')
        ->and($c->join($room->id, 'u2', 'Bob')['toast'])->not->toBe('lobby.joined_toast');

    // end-early: host-only, two-step confirm
    for ($i = 0; $i < 4; $i++) {
        $c->addBot($room->id, 'host1');
    }
    $c->confirmDm($room->id, 'host1');
    $c->start($room->id);
    $gameId = (string) $c->rooms()->requireRoom($room->id)->lastGameId;

    [, $toast] = $c->endEarlyGo($gameId, 'intruder');
    expect($toast)->toBe('errors.only_host_action')
        ->and($c->store()->loadSnapshot($gameId)?->phase)->not->toBe(PhaseEnum::Ended);

    [$plans] = $c->endEarlyAsk($gameId, 'host1');
    $callbacks = [];
    foreach ($plans[0]->keyboard ?? [] as $row) {
        foreach ($row as $button) {
            $callbacks[] = $button['callback'];
        }
    }
    expect($callbacks)->toContain('m:endearlygo:'.$gameId);

    [$plans] = $c->endEarlyGo($gameId, 'host1');
    $finished = $c->store()->loadSnapshot($gameId);
    expect($finished?->phase)->toBe(PhaseEnum::Ended)
        ->and($finished?->result)->toBe(GameResultEnum::Cancelled)
        ->and($plans)->not->toBeEmpty();
});

it('replaces a mid-game leaver with a bot inheriting the exact seat state (BOT-6)', function () {
    [$c, $gameId] = hostGameIn('Leaver');

    // host leaves mid-game → seat is taken over, knowledge state untouched
    $before = $c->store()->loadSnapshot($gameId)?->seatByUser('host1');
    expect($before)->not->toBeNull()->and($before?->isBot)->toBeFalse();

    $result = $c->leave($c->rooms()->requireRoom((string) $c->store()->loadSnapshot($gameId)?->roomId)->id, 'host1');
    expect($result['plans'])->not->toBeEmpty();

    $after = $c->store()->loadSnapshot($gameId)?->seatByUser('host1');
    $botSeat = null;
    foreach ($c->store()->loadSnapshot($gameId)?->seats ?? [] as $seat) {
        if ($seat->seat === $before?->seat) {
            $botSeat = $seat;
        }
    }
    expect($botSeat)->not->toBeNull()
        ->and($botSeat?->isBot)->toBeTrue()
        ->and($botSeat?->userId)->not->toBe('host1')
        ->and($botSeat?->role)->toBe($before?->role)
        ->and($botSeat?->bullets)->toBe($before?->bullets)
        ->and($botSeat?->selfHealLeft)->toBe($before?->selfHealLeft)
        ->and($botSeat?->elderShield)->toBe($before?->elderShield)
        ->and($botSeat?->alive)->toBeTrue();

    // the replaced seat keeps playing: deadlines still advance the game
    CoordinatorFactory::$clock->advance(600);
    $plans = $c->advanceIfOverdue($gameId);
    expect($c->store()->loadSnapshot($gameId)->phaseNumber)->toBeGreaterThan(1);
});

it('styles confirm buttons success and kick buttons danger (GRP-12)', function () {
    $c = CoordinatorFactory::make();
    $room = $c->createRoom('group', '-100', 'Styles', 'host1', 'Host', 5, 8, [], 'en');
    $c->join($room->id, 'u2', 'Bob');

    $card = $c->lobbyCard($c->rooms()->requireRoom($room->id), 'host1');
    $styles = [];
    foreach ($card->keyboard ?? [] as $row) {
        foreach ($row as $button) {
            if (isset($button['style'])) {
                $styles[$button['callback']] = $button['style'];
            }
        }
    }
    expect(($styles['m:begingame:'.$room->id] ?? null))->toBe('success')
        ->and($styles['m:kick:'.$room->id.':u2'] ?? null)->toBe('danger')
        // non-host view has no host controls at all
        ->and($c->lobbyCard($c->rooms()->requireRoom($room->id), 'u2')->keyboard[0][0]['callback'] ?? null)
        ->toBe('m:leave:'.$room->id);
});

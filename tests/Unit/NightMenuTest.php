<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Core\Enums\PhaseEnum;
use BAGArt\TelegramBotMafia\Core\SeatState;
use BAGArt\TelegramBotMafia\Presentation\InterfacePresenter;
use BAGArt\TelegramBotMafia\Tests\Support\CoordinatorFactory;

function nightSeat(int $n, string $role, bool $isBot = false): SeatState
{
    return new SeatState(
        seat: $n,
        userId: $isBot ? 'bot:'.$n : 'u'.$n,
        name: 'P'.$n,
        isBot: $isBot,
        role: $role,
        bullets: in_array($role, ['sniper', 'bandit'], true) ? 1 : 0,
        selfHealLeft: $role === 'doctor' ? 1 : 0,
        elderShield: $role === 'elder',
    );
}

it('builds role-specific night menus for every MVP role (GRP-4)', function () {
    $c = CoordinatorFactory::make();
    $snapshot = new BAGArt\TelegramBotMafia\Core\GameSnapshot(
        gameId: 'g1',
        roomId: 'r1',
        chatId: null,
        locale: 'en',
        phase: PhaseEnum::Night,
        phaseNumber: 1,
        dayNumber: 0,
        deadlineAt: 100,
        mirrorOn: false,
        seats: [
            nightSeat(1, 'mafia', true),
            nightSeat(2, 'godfather'),
            nightSeat(3, 'doctor'),
            nightSeat(4, 'detective'),
            nightSeat(5, 'escort'),
            nightSeat(6, 'bodyguard'),
            nightSeat(7, 'journalist'),
            nightSeat(8, 'villager'),
            nightSeat(9, 'elder'),
        ],
    );

    $presenter = new InterfacePresenter($c->lang('en'), new BAGArt\TelegramBotMafia\Presentation\GameCardRenderer($c->lang('en')));

    // godfather (mafia team): targets exclude mafia teammates
    $menus = [];
    foreach ($presenter->phaseAnnounce($snapshot) as $plan) {
        $menus[] = $plan;
    }
    expect($menus)->not->toBeEmpty();

    $byUser = [];
    foreach ($presenter->phaseAnnounce($snapshot) as $plan) {
        $byUser[$plan->chatId][] = $plan;
    }

    // godfather sees every other seat
    $gfTargets = [];
    foreach ($byUser['u2'][1]->keyboard ?? [] as $row) {
        foreach ($row as $b) {
            if (preg_match('/m:n:g1:(\d+)/', (string) $b['callback'], $m)) {
                $gfTargets[] = (int) $m[1];
            }
        }
    }
    expect($gfTargets)->toContain(1)
        ->and($gfTargets)->not->toContain(2);

    // doctor may self-heal and has the skip option
    $docTargets = [];
    $hasSkip = false;
    foreach ($byUser['u3'][1]->keyboard ?? [] as $row) {
        foreach ($row as $b) {
            if ((string) $b['callback'] === 'm:skipn:g1') {
                $hasSkip = true;
            }
            if (preg_match('/m:n:g1:(\d+)/', (string) $b['callback'], $m)) {
                $docTargets[] = (int) $m[1];
            }
        }
    }
    expect($docTargets)->toContain(3)
        ->and($hasSkip)->toBeTrue();

    // detective/escort/bodyguard/journalist get target menus; villager and
    // elder get none
    foreach (['u4', 'u5', 'u6', 'u7'] as $user) {
        expect($byUser[$user])->toHaveCount(2);
    }
    foreach (['u8', 'u9'] as $user) {
        expect($byUser[$user])->toHaveCount(1);
    }
});

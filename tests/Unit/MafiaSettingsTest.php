<?php

declare(strict_types=1);

use BAGArt\TelegramBotMafia\Settings\MafiaSettings;

it('ships platform defaults untouched', function () {
    $s = new MafiaSettings();

    expect($s->nightSeconds)->toBe(75)
        ->and($s->discussionSeconds)->toBe(150)
        ->and($s->voteSeconds)->toBe(45)
        ->and($s->locale)->toBe('ru')
        ->and($s->ballotMode)->toBe(MafiaSettings::BALLOT_OPEN)
        ->and($s->maxBots)->toBe(4)
        ->and($s->playersMin)->toBe(5)
        ->and($s->playersMax)->toBe(15);
});

it('reads a chat-level patch and clamps out-of-range values', function () {
    $s = MafiaSettings::fromArray([
        'night_seconds' => 9999,
        'discussion_seconds' => 1,
        'vote_seconds' => 30,
        'locale' => 'klingon',
        'ballot_mode' => 'secret',
        'max_bots' => -3,
        'players_min' => 7,
        'players_max' => 2,
    ]);

    expect($s->nightSeconds)->toBe(MafiaSettings::NIGHT_MAX)
        ->and($s->discussionSeconds)->toBe(MafiaSettings::DISCUSSION_MIN)
        ->and($s->voteSeconds)->toBe(30)
        ->and($s->locale)->toBe('ru')
        ->and($s->ballotMode)->toBe(MafiaSettings::BALLOT_SECRET)
        ->and($s->maxBots)->toBe(0)
        // players_max clamps up to players_min
        ->and($s->playersMin)->toBe(7)
        ->and($s->playersMax)->toBe(7);
});

it('ignores non-scalar junk and keeps defaults', function () {
    $s = MafiaSettings::fromArray([
        'night_seconds' => ['nope'],
        'locale' => 42,
        'ballot_mode' => true,
    ]);

    expect($s->nightSeconds)->toBe(75)
        ->and($s->locale)->toBe('ru')
        ->and($s->ballotMode)->toBe(MafiaSettings::BALLOT_OPEN);
});

it('ships OPS-4 kill-switch defaults: risky features off, existing behavior on', function () {
    $s = new MafiaSettings();

    expect($s->willsEnabled)->toBeFalse()
        ->and($s->reactionsEnabled)->toBeFalse()
        ->and($s->phasePingsEnabled)->toBeFalse()
        ->and($s->ghostPredictionsEnabled)->toBeFalse()
        ->and($s->pencilMarksEnabled)->toBeFalse()
        ->and($s->webAppButtonsEnabled)->toBeFalse()
        ->and($s->mirrorEnabled)->toBeTrue()
        ->and($s->speakingRelayEnabled)->toBeTrue();
});

it('roundtrips OPS-4 kill-switches from a chat-level patch', function () {
    $on = MafiaSettings::fromArray([
        'wills_enabled' => true,
        'reactions_enabled' => true,
        'phase_pings_enabled' => true,
        'ghost_predictions_enabled' => true,
        'pencil_marks_enabled' => true,
        'web_app_buttons_enabled' => true,
        'mirror_enabled' => false,
        'speaking_relay_enabled' => false,
    ]);

    expect($on->willsEnabled)->toBeTrue()
        ->and($on->reactionsEnabled)->toBeTrue()
        ->and($on->phasePingsEnabled)->toBeTrue()
        ->and($on->ghostPredictionsEnabled)->toBeTrue()
        ->and($on->pencilMarksEnabled)->toBeTrue()
        ->and($on->webAppButtonsEnabled)->toBeTrue()
        ->and($on->mirrorEnabled)->toBeFalse()
        ->and($on->speakingRelayEnabled)->toBeFalse();

    $off = MafiaSettings::fromArray([
        'wills_enabled' => false,
        'mirror_enabled' => false,
        'speaking_relay_enabled' => false,
    ]);

    expect($off->willsEnabled)->toBeFalse()
        ->and($off->reactionsEnabled)->toBeFalse()
        ->and($off->phasePingsEnabled)->toBeFalse()
        ->and($off->ghostPredictionsEnabled)->toBeFalse()
        ->and($off->pencilMarksEnabled)->toBeFalse()
        ->and($off->webAppButtonsEnabled)->toBeFalse()
        ->and($off->mirrorEnabled)->toBeFalse()
        ->and($off->speakingRelayEnabled)->toBeFalse();
});

it('falls back to OPS-4 kill-switch defaults when keys are absent', function () {
    $s = MafiaSettings::fromArray(['night_seconds' => 60]);

    expect($s->willsEnabled)->toBeFalse()
        ->and($s->reactionsEnabled)->toBeFalse()
        ->and($s->phasePingsEnabled)->toBeFalse()
        ->and($s->ghostPredictionsEnabled)->toBeFalse()
        ->and($s->pencilMarksEnabled)->toBeFalse()
        ->and($s->webAppButtonsEnabled)->toBeFalse()
        ->and($s->mirrorEnabled)->toBeTrue()
        ->and($s->speakingRelayEnabled)->toBeTrue();
});

it('coerces junk OPS-4 kill-switch values safely', function () {
    $s = MafiaSettings::fromArray([
        'wills_enabled' => 1,
        'reactions_enabled' => 'yes',
        'phase_pings_enabled' => ['junk'],
        'ghost_predictions_enabled' => null,
        'mirror_enabled' => 0,
        'speaking_relay_enabled' => '',
    ]);

    expect($s->willsEnabled)->toBeTrue()
        ->and($s->reactionsEnabled)->toBeTrue()
        ->and($s->phasePingsEnabled)->toBeFalse()
        ->and($s->ghostPredictionsEnabled)->toBeFalse()
        ->and($s->mirrorEnabled)->toBeFalse()
        ->and($s->speakingRelayEnabled)->toBeFalse();
});

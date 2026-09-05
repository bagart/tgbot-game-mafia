<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Presentation;

use BAGArt\TelegramBotMafia\Core\RoleCatalog;
use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Support\CallbackData;

/**
 * ONB-2 role encyclopedia: static reference cards over resources/roles.json +
 * ui.json roles section. No game context — chatId is a placeholder.
 */
final class RoleEncyclopedia
{
    /** Fixed display order, mirrors resources/roles.json roles map key order. */
    private const ROLE_ORDER = [
        'civilian',
        'detective',
        'doctor',
        'escort',
        'bodyguard',
        'witness',
        'journalist',
        'elder',
        'bomzh',
        'sniper',
        'mafia',
        'godfather',
        'turncoat',
        'maniac',
        'bandit',
        'satanist',
    ];

    /** Role id -> night_resolution_order entry; absence means no night action. */
    private const NIGHT_SLOTS = [
        'escort' => 'escort',
        'doctor' => 'doctor',
        'bodyguard' => 'bodyguard',
        'mafia' => 'mafia_kill',
        'godfather' => 'mafia_kill',
        'turncoat' => 'mafia_kill',
        'maniac' => 'maniac_kill',
        'bandit' => 'bandit_kill',
        'detective' => 'detective_check',
        'journalist' => 'journalist_check',
        'witness' => 'witness_observe',
    ];

    /** Team grouping order, mirrors resources/roles.json teams key order. */
    private const TEAM_ORDER = ['town', 'mafia', 'solo'];

    public function __construct(private readonly LangPack $lang)
    {
    }

    public function page(string $roleId): SendPlan
    {
        if (! in_array($roleId, self::ROLE_ORDER, true)) {
            throw new \InvalidArgumentException("Unknown role id: {$roleId}");
        }
        $index = (int) array_search($roleId, self::ROLE_ORDER, true);
        $text = implode("\n", [
            RoleCatalog::emoji($roleId).' <b>'.$this->lang->t('roles.'.$roleId.'.name', escape: false).'</b>',
            '<i>'.$this->lang->t('onb.team_'.RoleCatalog::team($roleId), escape: false)
                .' · '.$this->nightLine($roleId).'</i>',
            '',
            $this->lang->t('roles.'.$roleId.'.intro', [], escape: false),
            $this->lang->t('roles.'.$roleId.'.goal', [], escape: false),
            '',
            '💡 '.$this->lang->t('roles.'.$roleId.'.tips', escape: false),
        ]);

        return new SendPlan('0', $text, [
            [
                ['label' => '◀️', 'callback' => CallbackData::encode('rolepage', self::ROLE_ORDER[($index - 1 + 16) % 16])],
                ['label' => '▶️', 'callback' => CallbackData::encode('rolepage', self::ROLE_ORDER[($index + 1) % 16])],
            ],
            [
                ['label' => $this->lang->t('onb.back_to_rules'), 'callback' => CallbackData::encode('rules', '0')],
            ],
        ]);
    }

    public function index(): SendPlan
    {
        $rows = [];
        foreach (self::TEAM_ORDER as $team) {
            $row = [];
            foreach ($this->rolesOfTeam($team) as $roleId) {
                $row[] = [
                    'label' => RoleCatalog::emoji($roleId).' '.$this->lang->t('roles.'.$roleId.'.name'),
                    'callback' => CallbackData::encode('rolepage', $roleId),
                ];
                if (count($row) === 2) {
                    $rows[] = $row;
                    $row = [];
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        $rows[] = [
            ['label' => $this->lang->t('onb.back_to_rules'), 'callback' => CallbackData::encode('rules', '0')],
        ];

        return new SendPlan('0', $this->lang->t('onb.roles_title', escape: false), $rows);
    }

    private function nightLine(string $roleId): string
    {
        $slot = self::NIGHT_SLOTS[$roleId] ?? null;
        if ($slot === null) {
            return $this->lang->t('onb.no_night_action', escape: false);
        }
        $position = array_search($slot, RoleCatalog::nightOrder(), true);

        return $this->lang->t('onb.night_order_position', ['position' => $position + 1], escape: false);
    }

    /** @return list<string> */
    private function rolesOfTeam(string $team): array
    {
        return array_values(array_filter(
            self::ROLE_ORDER,
            fn (string $roleId): bool => RoleCatalog::team($roleId) === $team
        ));
    }
}

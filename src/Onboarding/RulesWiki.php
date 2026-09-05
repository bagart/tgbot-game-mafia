<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Onboarding;

use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;

/**
 * Paginated rules wiki (/rules). Sections are defined in code; texts come
 * from the wiki.* lang keys. Nav wraps around at the edges.
 */
final class RulesWiki
{
    private const SECTIONS = ['basics', 'roles_overview', 'night', 'voting', 'discipline', 'links'];

    public function __construct(
        private readonly LangPack $lang,
        private readonly string $chatId,
    ) {
    }

    public function pageCount(): int
    {
        return count(self::SECTIONS);
    }

    /** @param int $index any integer; wrapped into range */
    public function page(int $index): SendPlan
    {
        $total = $this->pageCount();
        $idx = (($index % $total) + $total) % $total;
        $section = self::SECTIONS[$idx];

        $text = implode("\n\n", [
            $this->lang->t('wiki.title', [], escape: false),
            '<b>'.$this->lang->t('wiki.'.$section.'.title', [], escape: false).'</b>',
            $this->lang->t('wiki.'.$section.'.body', [], escape: false),
            $this->lang->t('wiki.page_chip', ['current' => $idx + 1, 'total' => $total]),
        ]);

        return new SendPlan($this->chatId, $text, $this->keyboard($idx, $total));
    }

    public function firstPage(): SendPlan
    {
        return $this->page(0);
    }

    /** @return list<list<array{label: string, callback: string}>> */
    private function keyboard(int $idx, int $total): array
    {
        $prev = ($idx - 1 + $total) % $total;
        $next = ($idx + 1) % $total;
        $rows = [[
            ['label' => $this->lang->t('wiki.prev'), 'callback' => 'm:rules:'.$prev],
            ['label' => $this->lang->t('wiki.next'), 'callback' => 'm:rules:'.$next],
        ]];
        if ($idx === $total - 1) {
            $rows[] = [
                ['label' => $this->lang->t('wiki.open_roles'), 'callback' => 'm:rolepage:civilian'],
            ];
        }

        return $rows;
    }
}

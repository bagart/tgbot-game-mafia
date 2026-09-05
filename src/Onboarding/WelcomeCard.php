<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotMafia\Onboarding;

use BAGArt\TelegramBotMafia\I18n\LangPack;
use BAGArt\TelegramBotMafia\Presentation\SendPlan;

/**
 * Private-chat welcome card (/start). Offers the rules wiki and a language
 * switcher; the Quickplay / Rooms / Training buttons (W5 scope) are already
 * rendered but point at the m:onbsoon:* stub so users get an explicit
 * "coming soon" toast instead of dead or hidden UI.
 */
final class WelcomeCard
{
    private const LANG_CYCLE = ['en', 'ru', 'es', 'zh'];

    public function __construct(
        private readonly LangPack $lang,
        private readonly string $chatId,
        private readonly string $locale = 'en',
    ) {
    }

    public function card(): SendPlan
    {
        return new SendPlan(
            $this->chatId,
            implode("\n\n", [
                '<b>'.$this->lang->t('onb.welcome_title', [], escape: false).'</b>',
                $this->lang->t('onb.welcome_body', [], escape: false),
            ]),
            $this->keyboard(),
        );
    }

    /** Next locale in the en→ru→es→zh cycle used by the 🌍 button. */
    public static function nextLocale(string $current): string
    {
        $i = array_search($current, self::LANG_CYCLE, true);

        return self::LANG_CYCLE[$i === false ? 0 : ($i + 1) % count(self::LANG_CYCLE)];
    }

    /** @return list<list<array{label: string, callback: string}>> */
    private function keyboard(): array
    {
        return [
            [['label' => $this->lang->t('onb.btn_rules'), 'callback' => 'm:rules:0']],
            [['label' => $this->lang->t('onb.btn_language'), 'callback' => 'm:lang:'.self::nextLocale($this->locale)]],
            [['label' => $this->lang->t('onb.btn_quickplay'), 'callback' => 'm:onbsoon:quickplay']],
            [['label' => $this->lang->t('onb.btn_rooms'), 'callback' => 'm:onbsoon:rooms']],
            [['label' => $this->lang->t('onb.btn_training'), 'callback' => 'm:onbsoon:training']],
        ];
    }
}

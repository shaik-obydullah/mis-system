<?php

namespace App\Twig;

use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function formatMoney(mixed $amount): string
    {
        $currency = $this->settings->get('currency', 'USD');

        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

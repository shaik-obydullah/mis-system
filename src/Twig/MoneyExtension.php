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
            new TwigFilter('date_fmt', [$this, 'formatDate']),
            new TwigFilter('time_fmt', [$this, 'formatTime']),
            new TwigFilter('datetime_fmt', [$this, 'formatDateTime']),
        ];
    }

    public function formatMoney(mixed $amount): string
    {
        $currency = $this->settings->get('currency', 'USD');

        return $currency . ' ' . number_format((float) $amount, 2);
    }

    private function normalizeDate(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && '' !== trim($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function formatDate(mixed $value): string
    {
        $date = $this->normalizeDate($value);
        if (null === $date) {
            return '—';
        }

        return $date->format($this->settings->get('date_format', 'Y-m-d'));
    }

    public function formatTime(mixed $value): string
    {
        $date = $this->normalizeDate($value);
        if (null === $date) {
            return '—';
        }

        return $date->format($this->settings->get('time_format', 'H:i'));
    }

    public function formatDateTime(mixed $value): string
    {
        $date = $this->normalizeDate($value);
        if (null === $date) {
            return '—';
        }

        return $date->format(
            $this->settings->get('date_format', 'Y-m-d') . ' ' . $this->settings->get('time_format', 'H:i')
        );
    }
}

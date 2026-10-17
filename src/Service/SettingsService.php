<?php

namespace App\Service;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $setting = $this->em->getRepository(Setting::class)->find($name);

        return $setting ? $setting->getValue() : $default;
    }

    public function set(string $name, string $value): void
    {
        $setting = $this->em->getRepository(Setting::class)->find($name);

        if (null === $setting) {
            $setting = (new Setting())->setName($name);
            $this->em->persist($setting);
        }

        $setting->setValue($value);
        $this->em->flush();
    }
}

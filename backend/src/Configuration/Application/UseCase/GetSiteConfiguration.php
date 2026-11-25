<?php

declare(strict_types=1);

namespace App\Configuration\Application\UseCase;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\Port\ConfigurationCache;
use App\Configuration\Domain\Repository\ConfigurationRepository;

final readonly class GetSiteConfiguration
{
    public function __construct(
        private ConfigurationRepository $configurations,
        private ConfigurationCache $cache,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $cached = $this->cache->get('site');
        if ($cached !== null) {
            return $cached;
        }

        $stored = $this->configurations->find('site') ?? [];
        $settings = array_replace(ConfigurationDefaults::site(), $stored);
        $this->cache->set('site', $settings);

        return $settings;
    }
}

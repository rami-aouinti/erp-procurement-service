<?php

declare(strict_types=1);

namespace App\Configuration\Application\UseCase;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\Port\ConfigurationCache;
use App\Configuration\Domain\Repository\UserPreferenceRepository;

final readonly class GetUserPreferences
{
    public function __construct(
        private UserPreferenceRepository $preferences,
        private ConfigurationCache $cache,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(string $userId): array
    {
        // Hash the identity so raw user identifiers are never exposed in Redis keys.
        $cacheKey = 'user:' . hash('sha256', $userId);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $preferences = array_replace(
            ConfigurationDefaults::userPreferences(),
            $this->preferences->findForUser($userId),
        );
        $this->cache->set($cacheKey, $preferences, 120);

        return $preferences;
    }
}

<?php

declare(strict_types=1);

namespace App\Configuration\Application\UseCase;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\Port\ConfigurationCache;
use App\Configuration\Domain\Repository\UserPreferenceRepository;
use InvalidArgumentException;

final readonly class UpdateUserPreferences
{
    private const PALETTES = ['indigo', 'ocean', 'emerald', 'violet', 'rose', 'amber'];
    private const COLOR_MODES = ['light', 'dark', 'system'];

    public function __construct(
        private UserPreferenceRepository $preferences,
        private ConfigurationCache $cache,
    ) {
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function execute(string $userId, array $input): array
    {
        $unknown = array_diff(array_keys($input), array_keys(ConfigurationDefaults::userPreferences()));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported user preference(s): ' . implode(', ', $unknown));
        }

        $current = array_replace(ConfigurationDefaults::userPreferences(), $this->preferences->findForUser($userId));
        $updated = array_replace($current, $input);
        if (!in_array($updated['palette'], self::PALETTES, true)) {
            throw new InvalidArgumentException('Unsupported color palette.');
        }
        if (!in_array($updated['colorMode'], self::COLOR_MODES, true)) {
            throw new InvalidArgumentException('Unsupported color mode.');
        }

        $this->preferences->saveForUser($userId, $updated);
        $this->cache->invalidate('user:' . hash('sha256', $userId));

        return $updated;
    }
}

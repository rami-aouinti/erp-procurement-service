<?php

declare(strict_types=1);

namespace App\Configuration\Application\UseCase;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Domain\Repository\UserPreferenceRepository;

final readonly class GetUserPreferences
{
    public function __construct(private UserPreferenceRepository $preferences)
    {
    }

    /** @return array<string, mixed> */
    public function execute(string $userId): array
    {
        return array_replace(ConfigurationDefaults::userPreferences(), $this->preferences->findForUser($userId));
    }
}

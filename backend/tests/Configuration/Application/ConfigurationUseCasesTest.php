<?php

declare(strict_types=1);

namespace App\Tests\Configuration\Application;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\Port\ConfigurationCache;
use App\Configuration\Application\UseCase\GetUserPreferences;
use App\Configuration\Application\UseCase\UpdateSiteConfiguration;
use App\Configuration\Application\UseCase\UpdateUserPreferences;
use App\Configuration\Domain\Repository\ConfigurationRepository;
use App\Configuration\Domain\Repository\UserPreferenceRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigurationUseCasesTest extends TestCase
{
    public function testDefaultsAreAvailableBeforeTheFirstDatabaseWrite(): void
    {
        $defaults = ConfigurationDefaults::site();

        self::assertSame('ERP Procurement', $defaults['siteName']);
        self::assertSame('EUR', $defaults['currency']);
        self::assertSame(5, $defaults['itemsPerPage']);
        self::assertFalse($defaults['supplierForm']['requireEmail']);
        self::assertSame(['palette' => 'indigo', 'colorMode' => 'light'], ConfigurationDefaults::userPreferences());
    }

    public function testSiteConfigurationUpdateMergesNestedFormDefaultsAndInvalidatesCache(): void
    {
        $repository = new class implements ConfigurationRepository {
            public ?array $value = ['siteName' => 'Existing ERP', 'supplierForm' => ['requireEmail' => true]];
            public ?string $actorId = null;

            public function find(string $key): ?array
            {
                return $key === 'site' ? $this->value : null;
            }

            public function save(string $key, array $value, string $actorId): void
            {
                $this->value = $value;
                $this->actorId = $actorId;
            }
        };
        $cache = new class implements ConfigurationCache {
            public array $invalidated = [];

            public function get(string $key): ?array
            {
                return null;
            }

            public function set(string $key, array $value, int $ttl = 300): void
            {
            }

            public function invalidate(string $key): void
            {
                $this->invalidated[] = $key;
            }
        };
        $useCase = new UpdateSiteConfiguration($repository, $cache);

        $updated = $useCase->execute(['supplierForm' => ['requirePhone' => true]], 'keycloak-user-123');

        self::assertSame('Existing ERP', $updated['siteName']);
        self::assertTrue($updated['supplierForm']['requireEmail']);
        self::assertTrue($updated['supplierForm']['requirePhone']);
        self::assertFalse($updated['supplierForm']['requireTaxNumber']);
        self::assertFalse($updated['supplierForm']['requireContactPerson']);
        self::assertSame('keycloak-user-123', $repository->actorId);
        self::assertSame(['site'], $cache->invalidated);
    }

    public function testSiteConfigurationRejectsUnsupportedCurrency(): void
    {
        $repository = new class implements ConfigurationRepository {
            public function find(string $key): ?array
            {
                return null;
            }

            public function save(string $key, array $value, string $actorId): void
            {
            }
        };
        $cache = new class implements ConfigurationCache {
            public function get(string $key): ?array
            {
                return null;
            }

            public function set(string $key, array $value, int $ttl = 300): void
            {
            }

            public function invalidate(string $key): void
            {
            }
        };
        $useCase = new UpdateSiteConfiguration($repository, $cache);

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(['currency' => 'BTC'], 'admin-user');
    }

    public function testSiteConfigurationRejectsUnknownFields(): void
    {
        $repository = new class implements ConfigurationRepository {
            public function find(string $key): ?array
            {
                return null;
            }

            public function save(string $key, array $value, string $actorId): void
            {
            }
        };
        $cache = new class implements ConfigurationCache {
            public function get(string $key): ?array
            {
                return null;
            }

            public function set(string $key, array $value, int $ttl = 300): void
            {
            }

            public function invalidate(string $key): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        (new UpdateSiteConfiguration($repository, $cache))->execute(['databasePassword' => 'not-allowed'], 'admin-user');
    }

    public function testUserPreferencesHaveDefaultsAndAreStoredPerUser(): void
    {
        $repository = new class implements UserPreferenceRepository {
            private array $values = [];

            public function findForUser(string $userId): array
            {
                return $this->values[$userId] ?? [];
            }

            public function saveForUser(string $userId, array $preferences): void
            {
                $this->values[$userId] = $preferences;
            }
        };
        $get = new GetUserPreferences($repository);
        $update = new UpdateUserPreferences($repository);

        self::assertSame(['palette' => 'indigo', 'colorMode' => 'light'], $get->execute('user-a'));
        $saved = $update->execute('user-a', ['palette' => 'ocean', 'colorMode' => 'dark']);

        self::assertSame(['palette' => 'ocean', 'colorMode' => 'dark'], $saved);
        self::assertSame(['palette' => 'indigo', 'colorMode' => 'light'], $get->execute('user-b'));
        self::assertSame($saved, $get->execute('user-a'));
    }

    public function testUserPreferencesRejectUnsupportedPalette(): void
    {
        $repository = new class implements UserPreferenceRepository {
            public function findForUser(string $userId): array
            {
                return [];
            }

            public function saveForUser(string $userId, array $preferences): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        (new UpdateUserPreferences($repository))->execute('user-a', ['palette' => 'custom-unsafe']);
    }
}

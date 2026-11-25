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
        self::assertSame(10, $defaults['itemsPerPage']);
        self::assertFalse($defaults['supplierForm']['requireEmail']);
        self::assertSame(['palette' => 'indigo', 'colorMode' => 'light'], ConfigurationDefaults::userPreferences());
    }

    public function testSiteConfigurationUpdateMergesNestedFormDefaultsAndInvalidatesCache(): void
    {
        $repository = new InMemoryConfigurationRepository();
        $repository->value = [
            'siteName' => 'Existing ERP',
            'supplierForm' => ['requireEmail' => true],
        ];
        $cache = new InMemoryConfigurationCache();
        $useCase = new UpdateSiteConfiguration($repository, $cache);

        $updated = $useCase->execute([
            'supplierForm' => ['requirePhone' => true],
        ], 'keycloak-user-123');

        self::assertSame('Existing ERP', $updated['siteName']);
        self::assertTrue($updated['supplierForm']['requireEmail']);
        self::assertTrue($updated['supplierForm']['requirePhone']);
        self::assertFalse($updated['supplierForm']['requireTaxNumber']);
        self::assertSame('keycloak-user-123', $repository->actorId);
        self::assertSame(['site'], $cache->invalidated);
    }

    public function testSiteConfigurationRejectsUnsupportedCurrency(): void
    {
        $useCase = new UpdateSiteConfiguration(new InMemoryConfigurationRepository(), new InMemoryConfigurationCache());

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(['currency' => 'BTC'], 'admin-user');
    }

    public function testSiteConfigurationRejectsUnknownFields(): void
    {
        $useCase = new UpdateSiteConfiguration(new InMemoryConfigurationRepository(), new InMemoryConfigurationCache());

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(['databasePassword' => 'not-allowed'], 'admin-user');
    }

    public function testUserPreferencesHaveDefaultsAndAreStoredPerUser(): void
    {
        $repository = new InMemoryUserPreferenceRepository();
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
        $useCase = new UpdateUserPreferences(new InMemoryUserPreferenceRepository());

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute('user-a', ['palette' => 'custom-unsafe']);
    }
}

final class InMemoryConfigurationRepository implements ConfigurationRepository
{
    public ?array $value = null;
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
}

final class InMemoryUserPreferenceRepository implements UserPreferenceRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $values = [];

    public function findForUser(string $userId): array
    {
        return $this->values[$userId] ?? [];
    }

    public function saveForUser(string $userId, array $preferences): void
    {
        $this->values[$userId] = $preferences;
    }
}

final class InMemoryConfigurationCache implements ConfigurationCache
{
    /** @var list<string> */
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
}

<?php

declare(strict_types=1);

namespace App\Tests\Configuration\Integration;

use App\Configuration\Domain\Repository\ConfigurationRepository;
use App\Configuration\Domain\Repository\UserPreferenceRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OracleConfigurationRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private ConfigurationRepository $configurationRepository;
    private UserPreferenceRepository $preferenceRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->configurationRepository = static::getContainer()->get(ConfigurationRepository::class);
        $this->preferenceRepository = static::getContainer()->get(UserPreferenceRepository::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testGlobalConfigurationCanBeInsertedUpdatedAndReadFromOracle(): void
    {
        $key = 'integration-' . bin2hex(random_bytes(8));
        $initial = ['siteName' => 'Integration ERP', 'itemsPerPage' => 5];
        $updated = ['siteName' => 'Updated Integration ERP', 'itemsPerPage' => 25];

        self::assertNull($this->configurationRepository->find($key));

        $this->configurationRepository->save($key, $initial, 'integration-admin');
        self::assertSame($initial, $this->configurationRepository->find($key));

        $this->configurationRepository->save($key, $updated, 'integration-admin');
        self::assertSame($updated, $this->configurationRepository->find($key));
    }

    public function testUserPreferencesRemainIsolatedBetweenUsers(): void
    {
        $userA = 'integration-user-a-' . bin2hex(random_bytes(6));
        $userB = 'integration-user-b-' . bin2hex(random_bytes(6));

        $this->preferenceRepository->saveForUser($userA, [
            'palette' => 'ocean',
            'colorMode' => 'dark',
        ]);
        $this->preferenceRepository->saveForUser($userB, [
            'palette' => 'indigo',
            'colorMode' => 'light',
        ]);

        self::assertEquals(['palette' => 'ocean', 'colorMode' => 'dark'], $this->preferenceRepository->findForUser($userA));
        self::assertEquals(['palette' => 'indigo', 'colorMode' => 'light'], $this->preferenceRepository->findForUser($userB));
    }

    public function testUserPreferenceUpdatesAreIdempotentAndDoNotDuplicateRows(): void
    {
        $userId = 'integration-user-' . bin2hex(random_bytes(6));
        $preferences = ['palette' => 'ocean', 'colorMode' => 'dark'];

        $this->preferenceRepository->saveForUser($userId, $preferences);
        $this->preferenceRepository->saveForUser($userId, ['palette' => 'indigo']);
        $this->preferenceRepository->saveForUser($userId, ['colorMode' => 'light']);

        self::assertEquals(['palette' => 'indigo', 'colorMode' => 'light'], $this->preferenceRepository->findForUser($userId));
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM app_user_preferences WHERE user_id = :userId',
                ['userId' => $userId],
            ),
        );
    }
}

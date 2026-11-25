<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Integration;

use App\Supplier\Application\Port\SupplierSearchIndex;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Oracle integration coverage for the supplier onboarding state machine.
 *
 * Tests use unique supplier records and an outer transaction so no fixture or
 * existing supplier data is modified. Elasticsearch is cleaned separately.
 */
final class SupplierOnboardingOracleTest extends KernelTestCase
{
    private Connection $connection;
    private HttpKernelInterface $httpKernel;
    private string $email;
    private ?int $supplierId = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->httpKernel = static::getContainer()->get('http_kernel');
        $this->connection->beginTransaction();
        $this->email = sprintf('supplier-onboarding-%s@example.test', bin2hex(random_bytes(8)));
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        if ($this->supplierId !== null) {
            try {
                static::getContainer()->get(SupplierSearchIndex::class)->delete($this->supplierId);
            } catch (\Throwable) {
                // Elasticsearch is outside the Oracle transaction.
            }
        }

        parent::tearDown();
    }

    public function testCandidateCanBeSubmittedAndApprovedAtomicallyInOracle(): void
    {
        $created = $this->request('POST', '/api/supplier-onboarding', [
            'name' => 'Onboarding Oracle Happy Path',
            'email' => $this->email,
            'city' => 'Köln',
            'country' => 'DE',
        ], ['supplier-onboarding-submit']);

        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $candidate = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->supplierId = (int) $candidate['supplierId'];
        self::assertSame('PENDING_APPROVAL', $candidate['onboardingStatus']);
        self::assertSame('INACTIVE', $candidate['operationalStatus']);

        $submitted = $this->connection->fetchAssociative(
            'SELECT onboarding_status, version FROM supplier_onboarding WHERE supplier_id = :id',
            ['id' => $this->supplierId],
        );
        self::assertSame('PENDING_APPROVAL', $submitted['ONBOARDING_STATUS']);
        self::assertSame(2, (int) $submitted['VERSION']);

        $approved = $this->request(
            'POST',
            '/api/supplier-onboarding/' . $this->supplierId . '/approve',
            ['reason' => 'Compliance checks completed.'],
            ['supplier-reviewer'],
        );
        self::assertSame(Response::HTTP_OK, $approved->getStatusCode(), (string) $approved->getContent());
        self::assertSame('APPROVED', json_decode((string) $approved->getContent(), true, 512, JSON_THROW_ON_ERROR)['status']);

        $supplierStatus = $this->connection->fetchOne(
            'SELECT status FROM suppliers WHERE id = :id',
            ['id' => $this->supplierId],
        );
        self::assertSame('ACTIVE', $supplierStatus);

        $workflow = $this->connection->fetchAssociative(
            'SELECT onboarding_status, version, decision_reason FROM supplier_onboarding WHERE supplier_id = :id',
            ['id' => $this->supplierId],
        );
        self::assertSame('APPROVED', $workflow['ONBOARDING_STATUS']);
        self::assertSame(3, (int) $workflow['VERSION']);
        self::assertSame('Compliance checks completed.', $workflow['DECISION_REASON']);

        self::assertSame(
            ['SupplierOnboardingSubmitted', 'SupplierOnboardingApproved'],
            $this->connection->fetchFirstColumn(
                'SELECT event_type FROM supplier_outbox_events WHERE aggregate_id = :id ORDER BY occurred_at, event_type',
                ['id' => $this->supplierId],
            ),
        );
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_onboarding_history WHERE supplier_id = :id',
                ['id' => $this->supplierId],
            ),
        );
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM supplier_audit_logs WHERE supplier_id = :id AND entity_type = 'SUPPLIER_ONBOARDING'",
                ['id' => $this->supplierId],
            ),
        );
    }

    public function testDecisionFailureRollsBackWorkflowHistoryAuditAndOutboxTogether(): void
    {
        $created = $this->request('POST', '/api/supplier-onboarding', [
            'name' => 'Onboarding Oracle Rollback',
            'email' => $this->email,
        ], ['supplier-onboarding-submit']);

        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $candidate = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->supplierId = (int) $candidate['supplierId'];

        // Oracle actor_name is VARCHAR2(255). This deliberately causes the
        // history insert to fail after the workflow UPDATE, exercising rollback
        // of the whole decision transaction without schema changes or DDL.
        try {
            $this->request(
                'POST',
                '/api/supplier-onboarding/' . $this->supplierId . '/approve',
                ['reason' => 'This decision must roll back.'],
                ['supplier-reviewer'],
                str_repeat('x', 256),
            );
        } catch (\Throwable) {
            // Depending on the configured exception listener, Oracle errors are
            // either converted to HTTP 500 or propagated to the test process.
        }

        $workflowStatus = $this->connection->fetchOne(
            'SELECT onboarding_status FROM supplier_onboarding WHERE supplier_id = :id',
            ['id' => $this->supplierId],
        );
        self::assertSame('PENDING_APPROVAL', $workflowStatus);
        self::assertSame(
            'INACTIVE',
            $this->connection->fetchOne('SELECT status FROM suppliers WHERE id = :id', ['id' => $this->supplierId]),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_onboarding_history WHERE supplier_id = :id',
                ['id' => $this->supplierId],
            ),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM supplier_audit_logs WHERE supplier_id = :id AND entity_type = 'SUPPLIER_ONBOARDING'",
                ['id' => $this->supplierId],
            ),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_outbox_events WHERE aggregate_id = :id',
                ['id' => $this->supplierId],
            ),
        );
    }

    /** @param array<string, mixed>|null $payload @param list<string> $roles */
    private function request(
        string $method,
        string $uri,
        ?array $payload,
        array $roles,
        ?string $username = null,
    ): Response {
        $content = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, server: ['CONTENT_TYPE' => 'application/json'], content: $content);
        $request->attributes->set('_keycloak_user', [
            'id' => 'supplier-onboarding-integration-user',
            'username' => $username ?? 'supplier-onboarding-integration',
            'roles' => $roles,
        ]);

        return $this->httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
    }
}

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
 * End-to-end test for the real Oracle -> outbox -> RabbitMQ -> Elasticsearch flow.
 *
 * Unlike SupplierApiTest, this test deliberately does not wrap the test in a
 * database transaction: the publisher and consumer run in separate processes
 * and must be able to observe committed rows.
 *
 * Requires the local Docker Compose stack, including the publisher and consumer.
 */
final class SupplierEventPipelineTest extends KernelTestCase
{
    private Connection $connection;
    private HttpKernelInterface $httpKernel;
    private string $email;
    private string $marker;
    private ?int $supplierId = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->httpKernel = static::getContainer()->get('http_kernel');
        $token = bin2hex(random_bytes(6));
        $this->email = sprintf('supplier-pipeline-%s@example.test', $token);
        $this->marker = 'PipelineIntegration' . $token;
    }

    protected function tearDown(): void
    {
        if ($this->supplierId !== null) {
            try {
                $this->request('DELETE', '/api/suppliers/' . $this->supplierId);
            } catch (\Throwable) {
                // Preserve the original test failure; cleanup is best effort.
            }
        }

        parent::tearDown();
    }

    public function testCommittedSupplierEventsReachElasticsearchThroughRabbitMq(): void
    {
        $created = $this->request('POST', '/api/suppliers', [
            'name' => 'Pipeline Integration ' . $this->marker,
            'email' => $this->email,
            'city' => 'Köln',
            'status' => 'ACTIVE',
        ]);
        self::assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());

        $supplier = json_decode((string) $created->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->supplierId = (int) $supplier['id'];

        $this->waitForOutboxStatus('SupplierCreated', 'PUBLISHED');
        $this->waitForSearchResult($this->marker, true);
        $this->waitForConsumedEvent('SupplierCreated');

        $updated = $this->request('PUT', '/api/suppliers/' . $this->supplierId, [
            'name' => 'Pipeline Updated ' . $this->marker,
            'email' => $this->email,
            'city' => 'Bonn',
            'status' => 'BLOCKED',
        ]);
        self::assertSame(Response::HTTP_OK, $updated->getStatusCode(), (string) $updated->getContent());

        $this->waitForOutboxStatus('SupplierUpdated', 'PUBLISHED');
        $this->waitForSearchResult('Pipeline Updated ' . $this->marker, true);
        $this->waitForConsumedEvent('SupplierUpdated');

        $deleted = $this->request('DELETE', '/api/suppliers/' . $this->supplierId);
        self::assertSame(Response::HTTP_NO_CONTENT, $deleted->getStatusCode());
        $this->waitForOutboxStatus('SupplierDeleted', 'PUBLISHED');
        $this->waitForSearchResult($this->marker, false);
        $this->waitForConsumedEvent('SupplierDeleted');
        $this->supplierId = null;
    }

    private function waitForOutboxStatus(string $eventType, string $expectedStatus): void
    {
        $deadline = microtime(true) + 30;
        do {
            $status = $this->connection->fetchOne(
                'SELECT status FROM supplier_outbox_events WHERE aggregate_id = :id AND event_type = :type ORDER BY occurred_at DESC FETCH FIRST 1 ROWS ONLY',
                ['id' => $this->supplierId, 'type' => $eventType],
            );

            if ($status === $expectedStatus) {
                return;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf(
            'Supplier outbox event %s did not reach status %s within 30 seconds; last status: %s.',
            $eventType,
            $expectedStatus,
            is_string($status) ? $status : 'not found',
        ));
    }

    private function waitForConsumedEvent(string $eventType): void
    {
        $deadline = microtime(true) + 30;
        do {
            $status = $this->connection->fetchOne(
                'SELECT consumed.status
                 FROM supplier_consumed_events consumed
                 INNER JOIN supplier_outbox_events outbox ON outbox.event_id = consumed.event_id
                 WHERE outbox.aggregate_id = :id AND outbox.event_type = :type
                 ORDER BY outbox.occurred_at DESC
                 FETCH FIRST 1 ROWS ONLY',
                ['id' => $this->supplierId, 'type' => $eventType],
            );

            if ($status === 'PROCESSED') {
                return;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf(
            'Supplier event %s did not reach PROCESSED in the idempotency ledger within 30 seconds; last status: %s.',
            $eventType,
            is_string($status) ? $status : 'not found',
        ));
    }

    private function waitForSearchResult(string $query, bool $expected): void
    {
        $search = static::getContainer()->get(SupplierSearchIndex::class);
        $deadline = microtime(true) + 30;
        do {
            $results = $search->search($query);

            $found = false;
            foreach ($results as $result) {
                if ((int) ($result['id'] ?? 0) === $this->supplierId || ($this->supplierId === null && ($result['email'] ?? null) === $this->email)) {
                    $found = true;
                    break;
                }
            }

            if ($found === $expected) {
                return;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf('Elasticsearch did not reach the expected state for query "%s".', $query));
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, string $uri, ?array $payload = null): Response
    {
        $content = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        return $this->httpKernel->handle($request, HttpKernelInterface::MAIN_REQUEST);
    }
}

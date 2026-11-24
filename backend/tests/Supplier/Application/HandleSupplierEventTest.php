<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Application;

use App\Observability\MetricsRegistry;
use App\Supplier\Application\Port\SupplierEventDeduplication;
use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Application\Service\HandleSupplierEvent;
use App\Supplier\Domain\Repository\SupplierRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HandleSupplierEventTest extends TestCase
{
    public function testDelayedCreateEventDoesNotRecreateDeletedSupplier(): void
    {
        $suppliers = $this->createMock(SupplierRepository::class);
        $suppliers->expects(self::once())->method('findById')->with(42)->willReturn(null);

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->expects(self::once())->method('delete')->with(42);
        $search->expects(self::never())->method('index');

        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::once())->method('invalidate');

        $deduplication = $this->createMock(SupplierEventDeduplication::class);
        $deduplication->expects(self::once())->method('claim')
            ->with('event-42', 'SupplierCreated', 42)->willReturn('claim-token');
        $deduplication->expects(self::once())->method('markProcessed')->with('event-42', 'claim-token');
        $deduplication->expects(self::never())->method('release');

        $metrics = $this->metrics();
        $metrics->expects(self::once())->method('incrementSupplierEvent')->with('consumed');

        $handler = new HandleSupplierEvent($suppliers, $search, $cache, $deduplication, $metrics);
        $handler($this->event('SupplierCreated'));
    }

    public function testDuplicateDeliveryIsSkippedAfterFirstSuccessfulProcessing(): void
    {
        $suppliers = $this->createMock(SupplierRepository::class);
        $suppliers->expects(self::never())->method('findById');

        $search = $this->createMock(SupplierSearchIndex::class);
        $search->expects(self::once())->method('delete')->with(42);

        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::once())->method('invalidate');

        $deduplication = $this->createMock(SupplierEventDeduplication::class);
        $deduplication->expects(self::exactly(2))->method('claim')
            ->with('event-42', 'SupplierDeleted', 42)
            ->willReturnOnConsecutiveCalls('claim-token', null);
        $deduplication->expects(self::once())->method('markProcessed')->with('event-42', 'claim-token');
        $deduplication->expects(self::never())->method('release');

        $metricCounts = [];
        $metrics = $this->getMockBuilder(MetricsRegistry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['incrementSupplierEvent'])
            ->getMock();
        $metrics->expects(self::exactly(2))
            ->method('incrementSupplierEvent')
            ->willReturnCallback(static function (string $counter) use (&$metricCounts): void {
                $metricCounts[$counter] = ($metricCounts[$counter] ?? 0) + 1;
            });

        $handler = new HandleSupplierEvent($suppliers, $search, $cache, $deduplication, $metrics);
        $event = $this->event('SupplierDeleted');

        $handler($event);
        $handler($event);

        self::assertSame(['consumed' => 1, 'deduplicated' => 1], $metricCounts);
    }

    public function testUnsupportedEventIsReleasedAndNotCountedAsConsumed(): void
    {
        $suppliers = $this->createMock(SupplierRepository::class);
        $search = $this->createMock(SupplierSearchIndex::class);
        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::never())->method('invalidate');

        $deduplication = $this->createMock(SupplierEventDeduplication::class);
        $deduplication->expects(self::once())->method('claim')->willReturn('claim-token');
        $deduplication->expects(self::never())->method('markProcessed');
        $deduplication->expects(self::once())->method('release')
            ->with('event-42', 'claim-token', RuntimeException::class);

        $metrics = $this->metrics();
        $metrics->expects(self::never())->method('incrementSupplierEvent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported supplier event type');

        (new HandleSupplierEvent($suppliers, $search, $cache, $deduplication, $metrics))($this->event('SupplierArchived'));
    }

    public function testReadModelFailureReleasesClaimAndIsPropagated(): void
    {
        $suppliers = $this->createMock(SupplierRepository::class);
        $search = $this->createMock(SupplierSearchIndex::class);
        $search->expects(self::once())->method('delete')->with(42)
            ->willThrowException(new RuntimeException('Elasticsearch unavailable'));

        $cache = $this->createMock(SupplierListCache::class);
        $cache->expects(self::never())->method('invalidate');

        $deduplication = $this->createMock(SupplierEventDeduplication::class);
        $deduplication->expects(self::once())->method('claim')->willReturn('claim-token');
        $deduplication->expects(self::never())->method('markProcessed');
        $deduplication->expects(self::once())->method('release')
            ->with('event-42', 'claim-token', RuntimeException::class);

        $metrics = $this->metrics();
        $metrics->expects(self::never())->method('incrementSupplierEvent');

        try {
            (new HandleSupplierEvent($suppliers, $search, $cache, $deduplication, $metrics))($this->event('SupplierDeleted'));
            self::fail('The Elasticsearch exception should reach the RabbitMQ consumer.');
        } catch (RuntimeException $exception) {
            self::assertSame('Elasticsearch unavailable', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function event(string $type): array
    {
        return [
            'eventId' => 'event-42',
            'eventType' => $type,
            'aggregateId' => 42,
            'payload' => ['name' => 'stale payload is intentionally ignored'],
        ];
    }

    private function metrics(): MetricsRegistry
    {
        return $this->getMockBuilder(MetricsRegistry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['incrementSupplierEvent'])
            ->getMock();
    }
}

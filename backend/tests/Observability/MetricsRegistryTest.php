<?php

declare(strict_types=1);

namespace App\Tests\Observability;

use App\Observability\MetricsRegistry;
use PHPUnit\Framework\TestCase;

final class MetricsRegistryTest extends TestCase
{
    public function testRendersHttpCountersAndDurationHistogram(): void
    {
        $metrics = new MetricsRegistry();
        $metrics->observeRequest('GET', 'supplier_list', 200, 0.03);
        $metrics->observeRequest('POST', 'supplier_create', 201, 0.12);
        $metrics->observeRequest('GET', 'supplier_list', 500, 0.4);

        $output = $metrics->render();

        self::assertStringContainsString('erp_http_requests_total{method="GET",route="supplier_list",status="200"} 1', $output);
        self::assertStringContainsString('erp_http_requests_total{method="GET",route="supplier_list",status="500"} 1', $output);
        self::assertStringContainsString('erp_http_request_duration_seconds_bucket{method="GET",route="supplier_list",status="200",le="0.025"} 0', $output);
        self::assertStringContainsString('erp_http_request_duration_seconds_bucket{method="GET",route="supplier_list",status="200",le="0.050"} 1', $output);
        self::assertStringContainsString('erp_http_request_duration_seconds_count{method="GET",route="supplier_list",status="200"} 1', $output);
        self::assertStringContainsString('erp_php_memory_usage_bytes', $output);
    }

    public function testEscapesPrometheusLabelValues(): void
    {
        $metrics = new MetricsRegistry();
        $metrics->observeRequest('GET', "route\\with\nnewline", 200, 0.01);

        self::assertStringContainsString('route="route\\\\with\\nnewline"', $metrics->render());
    }

    public function testExposesSupplierEventMetricsEvenWhenRedisIsUnavailable(): void
    {
        $metrics = new MetricsRegistry('', 6379);
        $output = $metrics->render();

        self::assertStringContainsString('# TYPE erp_supplier_events_published_total counter', $output);
        self::assertStringContainsString('erp_supplier_events_published_total 0', $output);
        self::assertStringContainsString('# TYPE erp_supplier_events_consumed_total counter', $output);
        self::assertStringContainsString('# TYPE erp_supplier_events_deduplicated_total counter', $output);
        self::assertStringContainsString('erp_supplier_events_deduplicated_total 0', $output);
        self::assertStringContainsString('erp_supplier_events_failed_total 0', $output);
        self::assertStringContainsString('erp_supplier_events_dead_letter_total 0', $output);
        self::assertStringContainsString('# TYPE erp_supplier_outbox_pending_events gauge', $output);
        self::assertStringContainsString('erp_supplier_outbox_pending_events 0', $output);
        self::assertStringContainsString('erp_supplier_outbox_failed_events 0', $output);
        self::assertStringContainsString('erp_supplier_outbox_oldest_pending_age_seconds 0', $output);
    }
}

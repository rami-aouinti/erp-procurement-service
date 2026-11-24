<?php

declare(strict_types=1);

namespace App\Observability;

use Redis;
use Throwable;

final class MetricsRegistry
{
    private array $requests = [];
    private array $histograms = [];
    private readonly float $startedAt;
    private ?Redis $redis = null;

    private const SUPPLIER_COUNTERS = [
        'published' => 'erp_supplier_events_published_total',
        'consumed' => 'erp_supplier_events_consumed_total',
        'failed' => 'erp_supplier_events_failed_total',
        'dead_letter' => 'erp_supplier_events_dead_letter_total',
    ];

    private const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public function __construct(
        private readonly string $redisHost = '',
        private readonly int $redisPort = 6379,
    ) {
        $this->startedAt = microtime(true);
    }

    public function incrementSupplierEvent(string $counter): void
    {
        if (!isset(self::SUPPLIER_COUNTERS[$counter])) {
            return;
        }

        try {
            $this->redis()->incr('erp:metrics:' . self::SUPPLIER_COUNTERS[$counter]);
        } catch (Throwable) {
            $this->redis = null;
        }
    }

    public function setSupplierOutboxPending(int $count): void
    {
        try {
            $this->redis()->set('erp:metrics:erp_supplier_outbox_pending_events', max(0, $count));
        } catch (Throwable) {
            $this->redis = null;
        }
    }

    public function observeRequest(string $method, string $route, int $status, float $duration): void
    {
        $labels = $this->labels($method, $route, $status);
        $key = implode("\x1F", $labels);
        $this->requests[$key] = ($this->requests[$key] ?? 0) + 1;

        if (!isset($this->histograms[$key])) {
            $this->histograms[$key] = [
                'labels' => $labels,
                'count' => 0,
                'sum' => 0.0,
                'buckets' => array_fill_keys(array_map(static fn (float $bucket): string => sprintf('%.3F', $bucket), self::BUCKETS), 0),
            ];
        }

        $duration = max(0.0, $duration);
        $this->histograms[$key]['count']++;
        $this->histograms[$key]['sum'] += $duration;

        foreach (self::BUCKETS as $bucket) {
            if ($duration <= $bucket) {
                $this->histograms[$key]['buckets'][sprintf('%.3F', $bucket)]++;
            }
        }
    }

    public function render(): string
    {
        $output = [
            '# HELP erp_backend_uptime_seconds Time since the current PHP application process started.',
            '# TYPE erp_backend_uptime_seconds gauge',
            'erp_backend_uptime_seconds ' . $this->format(microtime(true) - $this->startedAt),
            '# HELP erp_php_memory_usage_bytes Current PHP memory usage in bytes.',
            '# TYPE erp_php_memory_usage_bytes gauge',
            'erp_php_memory_usage_bytes ' . memory_get_usage(true),
            '# HELP erp_php_memory_peak_bytes Peak PHP memory usage in bytes.',
            '# TYPE erp_php_memory_peak_bytes gauge',
            'erp_php_memory_peak_bytes ' . memory_get_peak_usage(true),
            '# HELP erp_http_requests_total Total HTTP responses handled by the backend.',
            '# TYPE erp_http_requests_total counter',
        ];

        foreach ($this->requests as $key => $count) {
            $output[] = 'erp_http_requests_total{' . implode(',', explode("\x1F", $key)) . '} ' . $count;
        }

        $output[] = '# HELP erp_http_request_duration_seconds HTTP response duration in seconds.';
        $output[] = '# TYPE erp_http_request_duration_seconds histogram';

        foreach ($this->histograms as $histogram) {
            $labels = implode(',', $histogram['labels']);
            foreach (self::BUCKETS as $bucket) {
                $bucketLabel = sprintf('%.3F', $bucket);
                $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="' . $bucketLabel . '"} ' . $histogram['buckets'][$bucketLabel];
            }
            $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="+Inf"} ' . $histogram['count'];
            $output[] = 'erp_http_request_duration_seconds_sum{' . $labels . '} ' . $this->format($histogram['sum']);
            $output[] = 'erp_http_request_duration_seconds_count{' . $labels . '} ' . $histogram['count'];
        }

        foreach (self::SUPPLIER_COUNTERS as $counter => $metric) {
            $output[] = '# HELP ' . $metric . ' Supplier asynchronous events ' . str_replace('_', ' ', $counter) . '.';
            $output[] = '# TYPE ' . $metric . ' counter';
            $output[] = $metric . ' ' . $this->readMetric($metric);
        }

        $output[] = '# HELP erp_supplier_outbox_pending_events Number of supplier outbox events waiting to be published.';
        $output[] = '# TYPE erp_supplier_outbox_pending_events gauge';
        $output[] = 'erp_supplier_outbox_pending_events ' . $this->readMetric('erp_supplier_outbox_pending_events');

        return implode("\n", $output) . "\n";
    }

    private function readMetric(string $name): string
    {
        try {
            $value = $this->redis()->get('erp:metrics:' . $name);

            return is_string($value) && is_numeric($value) ? $value : '0';
        } catch (Throwable) {
            $this->redis = null;

            return '0';
        }
    }

    private function redis(): Redis
    {
        if ($this->redis instanceof Redis && $this->redis->isConnected()) {
            return $this->redis;
        }

        if ($this->redisHost === '') {
            throw new \\RuntimeException('Redis metrics storage is not configured.');
        }

        $redis = new Redis();
        if (!$redis->connect($this->redisHost, $this->redisPort, 1.0)) {
            throw new \RuntimeException('Unable to connect to Redis metrics store.');
        }
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
        $this->redis = $redis;

        return $redis;
    }

    private function labels(string $method, string $route, int $status): array
    {
        return [
            'method="' . $this->escape($method) . '"',
            'route="' . $this->escape($route) . '"',
            'status="' . $status . '"',
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], $value);
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.') ?: '0';
    }
}

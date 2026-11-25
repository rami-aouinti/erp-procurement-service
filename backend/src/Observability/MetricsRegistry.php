<?php

declare(strict_types=1);

namespace App\Observability;

use Redis;
use Throwable;

class MetricsRegistry
{
    private array $requests = [];
    private array $histograms = [];
    private readonly float $startedAt;
    private ?Redis $redis = null;

    private const SUPPLIER_COUNTERS = [
        'published' => 'erp_supplier_events_published_total',
        'consumed' => 'erp_supplier_events_consumed_total',
        'deduplicated' => 'erp_supplier_events_deduplicated_total',
        'failed' => 'erp_supplier_events_failed_total',
        'dead_letter' => 'erp_supplier_events_dead_letter_total',
    ];

    private const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];
    private const HTTP_SERIES_SET = 'erp:metrics:http:series';
    private const HTTP_SERIES_PREFIX = 'erp:metrics:http:series:';

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
        $this->setGauge('erp_supplier_outbox_pending_events', $count);
    }

    public function setSupplierOutboxFailed(int $count): void
    {
        $this->setGauge('erp_supplier_outbox_failed_events', $count);
    }

    public function setSupplierOutboxOldestPendingAge(float $seconds): void
    {
        $this->setGauge('erp_supplier_outbox_oldest_pending_age_seconds', $seconds);
    }

    private function setGauge(string $name, int|float $value): void
    {
        try {
            $this->redis()->set('erp:metrics:' . $name, max(0, $value));
        } catch (Throwable) {
            $this->redis = null;
        }
    }

    public function observeRequest(string $method, string $route, int $status, float $duration): void
    {
        $duration = max(0.0, $duration);

        // HTTP metrics must be shared between independent PHP request lifecycles.
        // Redis is the source of truth in the application; in-memory storage keeps
        // the registry useful in isolated unit tests and when Redis is unavailable.
        if ($this->redisHost !== '') {
            try {
                $this->observeRequestInRedis($method, $route, $status, $duration);

                return;
            } catch (Throwable) {
                $this->redis = null;
            }
        }

        $this->observeRequestInMemory($method, $route, $status, $duration);
    }

    private function observeRequestInMemory(string $method, string $route, int $status, float $duration): void
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

        $this->histograms[$key]['count']++;
        $this->histograms[$key]['sum'] += $duration;

        foreach (self::BUCKETS as $bucket) {
            if ($duration <= $bucket) {
                $this->histograms[$key]['buckets'][sprintf('%.3F', $bucket)]++;
            }
        }
    }

    private function observeRequestInRedis(string $method, string $route, int $status, float $duration): void
    {
        $seriesId = hash('sha256', json_encode([$method, $route, $status], JSON_THROW_ON_ERROR));
        $key = self::HTTP_SERIES_PREFIX . $seriesId;
        $redis = $this->redis();

        $redis->sAdd(self::HTTP_SERIES_SET, $seriesId);
        $redis->hMSet($key, [
            'method' => $method,
            'route' => $route,
            'status' => (string) $status,
        ]);
        $redis->hIncrBy($key, 'count', 1);
        $redis->hIncrBy($key, 'duration_count', 1);
        $redis->hIncrByFloat($key, 'duration_sum', $duration);

        foreach (self::BUCKETS as $bucket) {
            if ($duration <= $bucket) {
                $redis->hIncrBy($key, 'bucket_' . sprintf('%.3F', $bucket), 1);
            }
        }
    }

    public function render(): string
    {
        $output = [
            '# HELP erp_backend_uptime_seconds Time since the current PHP application request started.',
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

        foreach ($this->readHttpSeries() as $series) {
            $labels = $this->formatLabels($series['method'], $series['route'], (int) $series['status']);
            $output[] = 'erp_http_requests_total{' . $labels . '} ' . $series['count'];
        }

        $output[] = '# HELP erp_http_request_duration_seconds HTTP response duration in seconds.';
        $output[] = '# TYPE erp_http_request_duration_seconds histogram';

        foreach ($this->readHttpSeries() as $series) {
            $labels = $this->formatLabels($series['method'], $series['route'], (int) $series['status']);
            foreach (self::BUCKETS as $bucket) {
                $bucketLabel = sprintf('%.3F', $bucket);
                $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="' . $bucketLabel . '"} ' . ($series['buckets'][$bucketLabel] ?? 0);
            }
            $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="+Inf"} ' . $series['duration_count'];
            $output[] = 'erp_http_request_duration_seconds_sum{' . $labels . '} ' . $this->format((float) $series['duration_sum']);
            $output[] = 'erp_http_request_duration_seconds_count{' . $labels . '} ' . $series['duration_count'];
        }

        foreach (self::SUPPLIER_COUNTERS as $counter => $metric) {
            $output[] = '# HELP ' . $metric . ' Supplier asynchronous events ' . str_replace('_', ' ', $counter) . '.';
            $output[] = '# TYPE ' . $metric . ' counter';
            $output[] = $metric . ' ' . $this->readMetric($metric);
        }

        $output[] = '# HELP erp_supplier_outbox_pending_events Number of supplier outbox events waiting to be published.';
        $output[] = '# TYPE erp_supplier_outbox_pending_events gauge';
        $output[] = 'erp_supplier_outbox_pending_events ' . $this->readMetric('erp_supplier_outbox_pending_events');
        $output[] = '# HELP erp_supplier_outbox_failed_events Number of supplier outbox events in FAILED state.';
        $output[] = '# TYPE erp_supplier_outbox_failed_events gauge';
        $output[] = 'erp_supplier_outbox_failed_events ' . $this->readMetric('erp_supplier_outbox_failed_events');
        $output[] = '# HELP erp_supplier_outbox_oldest_pending_age_seconds Age of the oldest pending supplier outbox event.';
        $output[] = '# TYPE erp_supplier_outbox_oldest_pending_age_seconds gauge';
        $output[] = 'erp_supplier_outbox_oldest_pending_age_seconds ' . $this->readMetric('erp_supplier_outbox_oldest_pending_age_seconds');

        return implode("\n", $output) . "\n";
    }

    private function readHttpSeries(): array
    {
        if ($this->redisHost === '') {
            return $this->inMemoryHttpSeries();
        }

        try {
            $redis = $this->redis();
            $seriesIds = $redis->sMembers(self::HTTP_SERIES_SET);
            $series = [];

            foreach ($seriesIds as $seriesId) {
                $data = $redis->hGetAll(self::HTTP_SERIES_PREFIX . $seriesId);
                if ($data === []) {
                    continue;
                }

                $buckets = [];
                foreach (self::BUCKETS as $bucket) {
                    $bucketLabel = sprintf('%.3F', $bucket);
                    $buckets[$bucketLabel] = (int) ($data['bucket_' . $bucketLabel] ?? 0);
                }

                $series[] = [
                    'method' => (string) ($data['method'] ?? 'UNKNOWN'),
                    'route' => (string) ($data['route'] ?? 'unmatched'),
                    'status' => (int) ($data['status'] ?? 0),
                    'count' => (int) ($data['count'] ?? 0),
                    'duration_count' => (int) ($data['duration_count'] ?? 0),
                    'duration_sum' => (float) ($data['duration_sum'] ?? 0),
                    'buckets' => $buckets,
                ];
            }

            return $series;
        } catch (Throwable) {
            $this->redis = null;

            return $this->inMemoryHttpSeries();
        }
    }

    private function inMemoryHttpSeries(): array
    {
        $series = [];
        foreach ($this->histograms as $key => $histogram) {
            $labels = [];
            foreach ($histogram['labels'] as $label) {
                preg_match('/^(method|route|status)="(.*)"$/', $label, $matches);
                $labels[$matches[1] ?? ''] = stripcslashes($matches[2] ?? '');
            }

            $series[] = [
                'method' => $labels['method'] ?? 'UNKNOWN',
                'route' => $labels['route'] ?? 'unmatched',
                'status' => (int) ($labels['status'] ?? 0),
                'count' => $this->requests[$key] ?? 0,
                'duration_count' => $histogram['count'],
                'duration_sum' => $histogram['sum'],
                'buckets' => $histogram['buckets'],
            ];
        }

        return $series;
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
            throw new RuntimeException('Unable to connect to Redis metrics store.');
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

    private function formatLabels(string $method, string $route, int $status): string
    {
        return implode(',', $this->labels($method, $route, $status));
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

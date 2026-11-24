<?php

declare(strict_types=1);

namespace App\Observability;

final class MetricsRegistry
{
    private array $requests = [];
    private array $histograms = [];
    private readonly float $startedAt;

    private const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public function __construct()
    {
        $this->startedAt = microtime(true);
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
                $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="' . $bucket . '"} ' . $histogram['buckets'][sprintf('%.3F', $bucket)];
            }
            $output[] = 'erp_http_request_duration_seconds_bucket{' . $labels . ',le="+Inf"} ' . $histogram['count'];
            $output[] = 'erp_http_request_duration_seconds_sum{' . $labels . '} ' . $this->format($histogram['sum']);
            $output[] = 'erp_http_request_duration_seconds_count{' . $labels . '} ' . $histogram['count'];
        }

        return implode("\n", $output) . "\n";
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

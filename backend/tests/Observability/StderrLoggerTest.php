<?php

declare(strict_types=1);

namespace App\Tests\Observability;

use App\Observability\StderrLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StderrLoggerTest extends TestCase
{
    public function testItWritesJsonAndFiltersSensitiveContext(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-log-');
        self::assertIsString($path);
        $previousErrorLog = ini_get('error_log');

        try {
            ini_set('error_log', $path);
            (new StderrLogger())->error('Database health check failed.', [
                'route' => 'api_database_health',
                'request_id' => 'request-123',
                'detail' => 'oracle password must never be logged',
                'exception' => new RuntimeException('sensitive exception message'),
            ]);
            $line = file_get_contents($path);
            self::assertIsString($line);
            $record = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('error', $record['level']);
            self::assertSame('Database health check failed.', $record['message']);
            self::assertSame('api_database_health', $record['context']['route']);
            self::assertSame(RuntimeException::class, $record['context']['exception_class']);
            self::assertArrayNotHasKey('detail', $record['context']);
            self::assertStringNotContainsString('password', $line);
            self::assertStringNotContainsString('sensitive exception message', $line);
        } finally {
            ini_set('error_log', is_string($previousErrorLog) ? $previousErrorLog : '');
            unlink($path);
        }
    }
}

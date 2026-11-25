<?php

declare(strict_types=1);

namespace App\Observability;

use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

final class StderrLogger extends AbstractLogger
{
    public function __construct(private readonly ?string $testLogPath = null)
    {
    }

    private const SENSITIVE_CONTEXT_KEYS = '/password|secret|token|authorization|cookie|iban|bank|email|phone|payload|body|detail|sql|query/i';

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $safeContext = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (preg_match(self::SENSITIVE_CONTEXT_KEYS, $key) === 1) {
                continue;
            }
            if ($value instanceof Throwable) {
                $safeContext['exception_class'] = $value::class;
            } elseif (is_string($value)) {
                $safeContext[$key] = substr($value, 0, 256);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $safeContext[$key] = $value;
            }
        }

        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $safeContext,
        ];
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $line = $json === false ? '{"level":"error","message":"Unable to encode application log"}' : $json;
        if ($this->testLogPath !== null) {
            file_put_contents($this->testLogPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

            return;
        }

        error_log($line);
    }
}

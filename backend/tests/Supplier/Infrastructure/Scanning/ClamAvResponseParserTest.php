<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Infrastructure\Scanning;

use App\Supplier\Application\Port\SupplierDocumentScanResult;
use App\Supplier\Infrastructure\Scanning\ClamAvResponseParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClamAvResponseParserTest extends TestCase
{
    public function testParsesCleanResult(): void
    {
        self::assertSame(
            SupplierDocumentScanResult::CLEAN,
            (new ClamAvResponseParser())->parse("stream: OK\n"),
        );
    }

    public function testParsesInfectedResultWithoutExposingSignatureAsStatus(): void
    {
        self::assertSame(
            SupplierDocumentScanResult::INFECTED,
            (new ClamAvResponseParser())->parse("stream: Eicar-Test-Signature FOUND\n"),
        );
    }

    public function testScannerErrorFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        (new ClamAvResponseParser())->parse("stream: size limit exceeded. ERROR\n");
    }

    public function testUnknownResponseFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        (new ClamAvResponseParser())->parse("unexpected response");
    }

    public function testUnexpectedOkSuffixDoesNotBecomeClean(): void
    {
        $this->expectException(RuntimeException::class);
        (new ClamAvResponseParser())->parse("stream: NOT OK");
    }
}

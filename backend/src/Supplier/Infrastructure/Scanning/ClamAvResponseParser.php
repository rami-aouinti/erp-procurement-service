<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Scanning;

use App\Supplier\Application\Port\SupplierDocumentScanResult;
use RuntimeException;

final class ClamAvResponseParser
{
    public function parse(string $response): SupplierDocumentScanResult
    {
        $response = trim($response);

        if ($response === 'stream: OK') {
            return SupplierDocumentScanResult::CLEAN;
        }

        if (preg_match('/^stream:\\s+.+\\sFOUND$/D', $response) === 1) {
            return SupplierDocumentScanResult::INFECTED;
        }

        if (preg_match('/^stream:\\s+.*\\s?ERROR$/D', $response) === 1) {
            throw new RuntimeException('ClamAV could not complete the document scan.');
        }

        throw new RuntimeException('ClamAV returned an invalid scan response.');
    }
}

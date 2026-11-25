<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

final readonly class StoredSupplierDocument
{
    public function __construct(
        public string $objectKey,
        public int $sizeBytes,
        public string $sha256,
    ) {
    }
}

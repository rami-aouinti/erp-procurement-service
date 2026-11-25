<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierDocumentStorage
{
    /** Store a file stream under a newly generated opaque key. Returns key and SHA-256. */
    public function put(string $sourcePath, int $maxBytes): StoredSupplierDocument;

    /** @return resource */
    public function read(string $objectKey);

    public function delete(string $objectKey): void;
}

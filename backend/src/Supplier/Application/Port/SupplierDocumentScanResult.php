<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

enum SupplierDocumentScanResult: string
{
    case CLEAN = 'CLEAN';
    case INFECTED = 'INFECTED';
}

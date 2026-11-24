<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Messaging;

use RuntimeException;

final class SupplierEventAlreadyProcessing extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Supplier event is already being processed by another worker.');
    }
}

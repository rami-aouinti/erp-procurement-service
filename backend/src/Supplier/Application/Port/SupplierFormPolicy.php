<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierFormPolicy
{
    public function isRequired(string $field): bool;
}

<?php

declare(strict_types=1);

namespace App\Supplier\Application\Exception;

final class SupplierValidationException extends \DomainException
{
    /** @param list<string> $violations */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(implode(' ', $violations));
    }

    /** @return list<string> */
    public function violations(): array
    {
        return $this->violations;
    }
}

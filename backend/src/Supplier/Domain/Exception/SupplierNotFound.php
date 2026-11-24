<?php
declare(strict_types=1);
namespace App\Supplier\Domain\Exception;
final class SupplierNotFound extends \DomainException
{
    public static function withId(int $id): self { return new self(sprintf('Supplier %d was not found.', $id)); }
}

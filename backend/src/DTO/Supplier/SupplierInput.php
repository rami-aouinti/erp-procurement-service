<?php

namespace App\DTO\Supplier;

final readonly class SupplierInput
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $taxNumber = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['name', 'email', 'phone', 'taxNumber'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !is_string($data[$field])) {
                throw new \InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $field));
            }
        }

        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Field "name" is required and must be a string.');
        }

        return new self(
            $data['name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['taxNumber'] ?? null,
        );
    }
}

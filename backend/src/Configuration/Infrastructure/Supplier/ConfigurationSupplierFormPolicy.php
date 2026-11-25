<?php

declare(strict_types=1);

namespace App\Configuration\Infrastructure\Supplier;

use App\Configuration\Application\UseCase\GetSiteConfiguration;
use App\Supplier\Application\Port\SupplierFormPolicy;

final readonly class ConfigurationSupplierFormPolicy implements SupplierFormPolicy
{
    public function __construct(private GetSiteConfiguration $configuration)
    {
    }

    public function isRequired(string $field): bool
    {
        $settings = $this->configuration->execute();
        $form = $settings['supplierForm'] ?? [];
        if (!is_array($form)) {
            return false;
        }

        $option = match ($field) {
            'email' => 'requireEmail',
            'phone' => 'requirePhone',
            'taxNumber' => 'requireTaxNumber',
            'contactPerson' => 'requireContactPerson',
            default => null,
        };

        return $option !== null && ($form[$option] ?? false) === true;
    }
}

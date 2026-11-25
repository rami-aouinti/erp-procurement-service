<?php

declare(strict_types=1);

namespace App\Configuration\Application;

final class ConfigurationDefaults
{
    /**
     * Defaults are deliberately code-owned: the application remains usable
     * before the first database write and after a cache/database recovery.
     *
     * @return array<string, mixed>
     */
    public static function site(): array
    {
        return [
            'siteName' => 'ERP Procurement',
            'companyName' => '',
            'companyEmail' => '',
            'companyPhone' => '',
            'companyAddress' => '',
            'currency' => 'EUR',
            'locale' => 'en',
            'timezone' => 'Europe/Berlin',
            'dateFormat' => 'Y-m-d',
            'itemsPerPage' => 10,
            'supplierForm' => [
                'requireEmail' => false,
                'requirePhone' => false,
                'requireTaxNumber' => false,
                'allowInactive' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function userPreferences(): array
    {
        return [
            'palette' => 'indigo',
            'colorMode' => 'light',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function schema(): array
    {
        return [
            [
                'key' => 'siteName',
                'label' => 'Application name',
                'type' => 'text',
                'group' => 'general',
                'required' => true,
                'maxLength' => 100,
            ],
            ['key' => 'companyName', 'label' => 'Company name', 'type' => 'text', 'group' => 'company'],
            ['key' => 'companyEmail', 'label' => 'Company email', 'type' => 'email', 'group' => 'company'],
            ['key' => 'companyPhone', 'label' => 'Company phone', 'type' => 'text', 'group' => 'company'],
            ['key' => 'companyAddress', 'label' => 'Company address', 'type' => 'textarea', 'group' => 'company'],
            [
                'key' => 'currency',
                'label' => 'Default currency',
                'type' => 'select',
                'group' => 'regional',
                'options' => ['EUR', 'USD', 'GBP', 'CHF'],
            ],
            [
                'key' => 'locale',
                'label' => 'Default language',
                'type' => 'select',
                'group' => 'regional',
                'options' => ['en', 'de', 'fr'],
            ],
            ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'group' => 'regional'],
            [
                'key' => 'dateFormat',
                'label' => 'Date format',
                'type' => 'select',
                'group' => 'regional',
                'options' => ['Y-m-d', 'd.m.Y', 'd/m/Y'],
            ],
            [
                'key' => 'itemsPerPage',
                'label' => 'Rows per page',
                'type' => 'number',
                'group' => 'forms',
                'min' => 5,
                'max' => 100,
            ],
            ['key' => 'supplierForm.requireEmail', 'label' => 'Require supplier email', 'type' => 'toggle', 'group' => 'supplierForm'],
            ['key' => 'supplierForm.requirePhone', 'label' => 'Require supplier phone', 'type' => 'toggle', 'group' => 'supplierForm'],
            ['key' => 'supplierForm.requireTaxNumber', 'label' => 'Require supplier tax number', 'type' => 'toggle', 'group' => 'supplierForm'],
            ['key' => 'supplierForm.allowInactive', 'label' => 'Allow inactive suppliers', 'type' => 'toggle', 'group' => 'supplierForm'],
        ];
    }
}

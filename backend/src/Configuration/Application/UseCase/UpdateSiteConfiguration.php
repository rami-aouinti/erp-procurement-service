<?php

declare(strict_types=1);

namespace App\Configuration\Application\UseCase;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\Port\ConfigurationCache;
use App\Configuration\Domain\Repository\ConfigurationRepository;
use InvalidArgumentException;

final readonly class UpdateSiteConfiguration
{
    private const ALLOWED_CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF'];
    private const ALLOWED_LOCALES = ['en', 'de', 'fr'];
    private const ALLOWED_DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'd/m/Y'];

    public function __construct(
        private ConfigurationRepository $configurations,
        private ConfigurationCache $cache,
    ) {
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function execute(array $input, string $actorId): array
    {
        $defaults = ConfigurationDefaults::site();
        $unknown = array_diff(array_keys($input), array_keys($defaults));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unsupported configuration field(s): ' . implode(', ', $unknown));
        }

        $current = array_replace($defaults, $this->configurations->find('site') ?? []);
        $updated = array_replace($current, $input);

        $this->assertString($updated, 'siteName', 100, true);
        foreach (['companyName' => 160, 'companyEmail' => 180, 'companyPhone' => 40, 'companyAddress' => 1000, 'timezone' => 80] as $field => $maxLength) {
            $this->assertString($updated, $field, $maxLength);
        }
        if ($updated['companyEmail'] !== '' && filter_var($updated['companyEmail'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Company email must be a valid email address.');
        }
        if (!in_array($updated['currency'], self::ALLOWED_CURRENCIES, true)) {
            throw new InvalidArgumentException('Unsupported default currency.');
        }
        if (!in_array($updated['locale'], self::ALLOWED_LOCALES, true)) {
            throw new InvalidArgumentException('Unsupported default language.');
        }
        if (!in_array($updated['dateFormat'], self::ALLOWED_DATE_FORMATS, true)) {
            throw new InvalidArgumentException('Unsupported date format.');
        }
        if (!is_int($updated['itemsPerPage']) || $updated['itemsPerPage'] < 5 || $updated['itemsPerPage'] > 100) {
            throw new InvalidArgumentException('Rows per page must be an integer between 5 and 100.');
        }
        if (!is_array($updated['supplierForm'] ?? null)) {
            throw new InvalidArgumentException('Supplier form configuration must be an object.');
        }
        $formDefaults = $defaults['supplierForm'];
        $unknownFormFields = array_diff(array_keys($updated['supplierForm']), array_keys($formDefaults));
        if ($unknownFormFields !== []) {
            throw new InvalidArgumentException('Unsupported supplier form option(s): ' . implode(', ', $unknownFormFields));
        }
        foreach ($formDefaults as $field => $default) {
            if (!is_bool($updated['supplierForm'][$field] ?? $default)) {
                throw new InvalidArgumentException(sprintf('Supplier form option "%s" must be boolean.', $field));
            }
        }

        $this->configurations->save('site', $updated, $actorId);
        $this->cache->invalidate('site');

        return $updated;
    }

    /** @param array<string, mixed> $data */
    private function assertString(array $data, string $field, int $maxLength, bool $required = false): void
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || ($required && trim($value) === '') || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('Configuration field "%s" is invalid.', $field));
        }
    }
}

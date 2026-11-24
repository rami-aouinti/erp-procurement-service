<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Iban
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', $value));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $normalized)) {
            throw new InvalidArgumentException('IBAN format is invalid.');
        }

        $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        if ($remainder !== 1) {
            throw new InvalidArgumentException('IBAN checksum is invalid.');
        }

        $this->value = $normalized;
    }
}

<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Supplier\Application\UseCase\ManageSupplierBankAccounts;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierBankAccount;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/suppliers/{id<\\d+>}/bank-accounts')]
final readonly class SupplierBankAccountController
{
    public function __construct(private ManageSupplierBankAccounts $accounts)
    {
    }

    #[Route('', name: 'api_supplier_bank_accounts_list', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        try {
            return new JsonResponse(array_map(self::serialize(...), $this->accounts->list($id)));
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        }
    }

    #[Route('', name: 'api_supplier_bank_accounts_add', methods: ['POST'])]
    public function add(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) {
                throw new InvalidArgumentException('A JSON object is required.');
            }

            $account = $this->accounts->add(
                $id,
                self::requiredString($data, 'accountHolder'),
                self::requiredString($data, 'iban'),
                self::optionalString($data, 'bic'),
                self::optionalString($data, 'bankName'),
                strtoupper(self::optionalString($data, 'currency') ?? 'EUR'),
                self::boolean($data, 'primary'),
            );

            return new JsonResponse(self::serialize($account), 201);
        } catch (SupplierNotFound) {
            return new JsonResponse(['error' => 'Supplier not found.'], 404);
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Field "%s" is required and must be a string.', $key));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $key));
        }

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $data */
    private static function boolean(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function serialize(SupplierBankAccount $account): array
    {
        return [
            'id' => $account->id,
            'supplierId' => $account->supplierId,
            'iban' => $account->maskedIban,
            'bic' => $account->bic,
            'bankName' => $account->bankName,
            'currency' => $account->currency,
            'primary' => $account->primary,
        ];
    }
}

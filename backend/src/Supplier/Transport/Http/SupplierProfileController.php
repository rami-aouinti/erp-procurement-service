<?php

declare(strict_types=1);

namespace App\Supplier\Transport\Http;

use App\Supplier\Application\DTO\SupplierProfileData;
use App\Supplier\Application\UseCase\GetSupplierProfile;
use App\Supplier\Application\UseCase\UpdateSupplierProfile;
use App\Supplier\Domain\Exception\SupplierNotFound;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/suppliers/{id<\\d+>}/profile')]
final class SupplierProfileController
{
    #[Route('', name: 'api_supplier_profile_get', methods: ['GET'])]
    public function get(int $id, GetSupplierProfile $useCase): JsonResponse
    {
        try {
            return new JsonResponse($this->serialize($useCase->execute($id)));
        } catch (SupplierNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
    }

    #[Route('', name: 'api_supplier_profile_update', methods: ['PUT'])]
    public function update(int $id, Request $request, UpdateSupplierProfile $useCase): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || array_is_list($payload)) {
                throw new InvalidArgumentException('A JSON object is required.');
            }

            $stringFields = ['legalName', 'registrationNumber', 'vatNumber', 'website', 'preferredLanguage', 'preferredCurrency'];
            foreach ($stringFields as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== null && !is_string($payload[$field])) {
                    throw new InvalidArgumentException(sprintf('Field "%s" must be a string or null.', $field));
                }
            }
            $terms = $payload['paymentTermsDays'] ?? 30;
            if (!is_int($terms)) {
                throw new InvalidArgumentException('Field "paymentTermsDays" must be an integer.');
            }

            $profile = $useCase->execute($id, new SupplierProfileData(
                $payload['legalName'] ?? null,
                $payload['registrationNumber'] ?? null,
                $payload['vatNumber'] ?? null,
                $payload['website'] ?? null,
                $payload['preferredLanguage'] ?? 'de',
                $payload['preferredCurrency'] ?? 'EUR',
                $terms,
            ));

            return new JsonResponse($this->serialize($profile));
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (SupplierNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(\App\Supplier\Domain\Model\SupplierProfile $profile): array
    {
        return [
            'supplierId' => $profile->supplierId,
            'legalName' => $profile->legalName,
            'registrationNumber' => $profile->registrationNumber,
            'vatNumber' => $profile->vatNumber,
            'website' => $profile->website,
            'preferredLanguage' => $profile->preferredLanguage,
            'preferredCurrency' => $profile->preferredCurrency,
            'paymentTermsDays' => $profile->paymentTermsDays,
        ];
    }
}

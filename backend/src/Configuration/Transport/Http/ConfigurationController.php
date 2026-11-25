<?php

declare(strict_types=1);

namespace App\Configuration\Transport\Http;

use App\Configuration\Application\ConfigurationDefaults;
use App\Configuration\Application\UseCase\GetSiteConfiguration;
use App\Configuration\Application\UseCase\GetUserPreferences;
use App\Configuration\Application\UseCase\UpdateSiteConfiguration;
use App\Configuration\Application\UseCase\UpdateUserPreferences;
use App\Security\Keycloak\KeycloakRoleGuard;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/configuration')]
final class ConfigurationController
{
    #[Route('/site', name: 'api_configuration_site_get', methods: ['GET'])]
    public function site(GetSiteConfiguration $useCase): JsonResponse
    {

        return new JsonResponse([
            'settings' => $useCase->execute(),
            'schema' => ConfigurationDefaults::schema(),
        ]);
    }

    #[Route('/site', name: 'api_configuration_site_update', methods: ['PUT'])]
    public function updateSite(
        Request $request,
        UpdateSiteConfiguration $useCase,
        KeycloakRoleGuard $roles,
    ): JsonResponse {
        $actor = $roles->currentActor();
        $roles->assertAnyRole('supplier-admin');

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || array_is_list($payload) || !is_array($payload['settings'] ?? null)) {
                throw new InvalidArgumentException('A settings object is required.');
            }

            return new JsonResponse([
                'settings' => $useCase->execute($payload['settings'], $actor['id']),
                'message' => 'Configuration saved.',
            ]);
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/me/preferences', name: 'api_configuration_preferences_get', methods: ['GET'])]
    public function preferences(GetUserPreferences $useCase, KeycloakRoleGuard $roles): JsonResponse
    {
        $actor = $roles->currentActor();

        return new JsonResponse(['preferences' => $useCase->execute($actor['id'])]);
    }

    #[Route('/me/preferences', name: 'api_configuration_preferences_update', methods: ['PUT'])]
    public function updatePreferences(
        Request $request,
        UpdateUserPreferences $useCase,
        KeycloakRoleGuard $roles,
    ): JsonResponse {
        $actor = $roles->currentActor();

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || array_is_list($payload) || !is_array($payload['preferences'] ?? null)) {
                throw new InvalidArgumentException('A preferences object is required.');
            }

            return new JsonResponse([
                'preferences' => $useCase->execute($actor['id'], $payload['preferences']),
                'message' => 'Preferences saved.',
            ]);
        } catch (JsonException | InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

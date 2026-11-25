<?php

declare(strict_types=1);

namespace App\Security\Keycloak;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class KeycloakRoleGuard
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function assertAnyRole(string ...$requiredRoles): void
    {
        $user = $this->requestStack->getCurrentRequest()?->attributes->get('_keycloak_user');
        if (!is_array($user) || !is_array($user['roles'] ?? null)) {
            throw new AccessDeniedHttpException('An authenticated Keycloak identity is required.');
        }

        if (array_intersect($requiredRoles, $user['roles']) === []) {
            throw new AccessDeniedHttpException('The authenticated user does not have the required role.');
        }
    }

    /** @return array{id: string, username: string, roles: list<string>} */
    public function currentActor(): array
    {
        $user = $this->requestStack->getCurrentRequest()?->attributes->get('_keycloak_user');
        if (!is_array($user) || !is_string($user['id'] ?? null)) {
            throw new AccessDeniedHttpException('An authenticated Keycloak identity is required.');
        }
        return $user;
    }
}

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

    public function hasAnyRole(string ...$requiredRoles): bool
    {
        $user = $this->requestStack->getCurrentRequest()?->attributes->get('_keycloak_user');
        if (!is_array($user) || !is_array($user['roles'] ?? null)) {
            return false;
        }

        return array_intersect($requiredRoles, $user['roles']) !== [];
    }

    public function assertAnyRole(string ...$requiredRoles): void
    {
        $user = $this->requestStack->getCurrentRequest()?->attributes->get('_keycloak_user');
        if (!is_array($user) || !is_array($user['roles'] ?? null)) {
            throw new AccessDeniedHttpException('An authenticated Keycloak identity is required.');
        }

        if (!$this->hasAnyRole(...$requiredRoles)) {
            throw new AccessDeniedHttpException('The authenticated user does not have the required role.');
        }
    }

    /** @return array{id: string, username: string, roles: list<string>} */
    public function currentActor(): array
    {
        $user = $this->requestStack->getCurrentRequest()?->attributes->get('_keycloak_user');
        if (
            !is_array($user)
            || !is_string($user['id'] ?? null)
            || !is_string($user['username'] ?? null)
            || !is_array($user['roles'] ?? null)
        ) {
            throw new AccessDeniedHttpException('An authenticated Keycloak identity is required.');
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'roles' => array_values(array_filter($user['roles'], 'is_string')),
        ];
    }
}

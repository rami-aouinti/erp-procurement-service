<?php

declare(strict_types=1);

namespace App\Security\Keycloak;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class KeycloakRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private KeycloakJwtAuthenticator $authenticator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (
            !str_starts_with($path, '/api/supplier-onboarding')
            && !str_starts_with($path, '/api/configuration')
        ) {
            return;
        }

        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new UnauthorizedHttpException('Bearer', 'A valid Keycloak access token is required.');
        }

        try {
            $claims = $this->authenticator->authenticate(trim($matches[1]));
        } catch (KeycloakUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(5, 'The identity provider is temporarily unavailable.', $exception);
        } catch (\RuntimeException | \JsonException) {
            throw new UnauthorizedHttpException('Bearer', 'The access token is invalid or expired.');
        }

        $username = $claims['preferred_username'] ?? $claims['email'] ?? $claims['sub'];
        if (!is_string($username) || $username === '') {
            $username = $claims['sub'];
        }
        $username = mb_substr($username, 0, 160);

        $request->attributes->set('_keycloak_user', [
            'id' => $claims['sub'],
            'username' => $username,
            'roles' => $this->roles($claims),
        ]);
    }

    /** @return list<string> */
    private function roles(array $claims): array
    {
        $realmAccess = $claims['realm_access'] ?? [];
        $roles = is_array($realmAccess) && is_array($realmAccess['roles'] ?? null)
            ? $realmAccess['roles']
            : [];
        $resourceAccess = $claims['resource_access'] ?? [];
        $apiClient = is_array($resourceAccess) ? ($resourceAccess['erp-procurement-api'] ?? []) : [];
        $apiClientRoles = is_array($apiClient) ? ($apiClient['roles'] ?? []) : [];
        if (is_array($apiClientRoles)) {
            $roles = array_merge($roles, $apiClientRoles);
        }

        return array_values(array_unique(array_filter($roles, 'is_string')));
    }
}

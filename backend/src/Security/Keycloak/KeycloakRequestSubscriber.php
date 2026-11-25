<?php

declare(strict_types=1);

namespace App\Security\Keycloak;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
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
        if (!str_starts_with($request->getPathInfo(), '/api/supplier-onboarding')) {
            return;
        }

        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new UnauthorizedHttpException('Bearer', 'A valid Keycloak access token is required.');
        }

        try {
            $claims = $this->authenticator->authenticate(trim($matches[1]));
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('Bearer', 'The access token is invalid or expired.');
        }

        $request->attributes->set('_keycloak_user', [
            'id' => $claims['sub'],
            'username' => $claims['preferred_username'] ?? $claims['email'] ?? $claims['sub'],
            'roles' => $this->roles($claims),
        ]);
    }

    /** @return list<string> */
    private function roles(array $claims): array
    {
        $roles = $claims['realm_access']['roles'] ?? [];
        foreach (($claims['resource_access'] ?? []) as $client) {
            if (is_array($client) && is_array($client['roles'] ?? null)) {
                $roles = array_merge($roles, $client['roles']);
            }
        }
        return array_values(array_unique(array_filter($roles, 'is_string')));
    }
}

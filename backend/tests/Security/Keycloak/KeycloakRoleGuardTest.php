<?php

declare(strict_types=1);

namespace App\Tests\Security\Keycloak;

use App\Security\Keycloak\KeycloakRoleGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class KeycloakRoleGuardTest extends TestCase
{
    public function testCurrentActorAndRequiredRoleComeFromVerifiedRequestAttributes(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        $request->attributes->set('_keycloak_user', [
            'id' => 'keycloak-subject-42',
            'username' => 'reviewer',
            'roles' => ['supplier-reviewer'],
        ]);
        $stack->push($request);

        $guard = new KeycloakRoleGuard($stack);
        $guard->assertAnyRole('supplier-reviewer', 'supplier-admin');

        self::assertSame('keycloak-subject-42', $guard->currentActor()['id']);
        self::assertSame('reviewer', $guard->currentActor()['username']);
    }

    public function testMissingRoleIsForbidden(): void
    {
        $stack = new RequestStack();
        $request = new Request();
        $request->attributes->set('_keycloak_user', [
            'id' => 'keycloak-subject-42',
            'username' => 'procurement-user',
            'roles' => ['procurement-user'],
        ]);
        $stack->push($request);

        $this->expectException(AccessDeniedHttpException::class);
        (new KeycloakRoleGuard($stack))->assertAnyRole('supplier-reviewer');
    }
}

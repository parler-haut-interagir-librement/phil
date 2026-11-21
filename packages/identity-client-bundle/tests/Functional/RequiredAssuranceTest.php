<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Application\Event\UserSignedIn;
use Phil\IdentityClientBundle\Infrastructure\Security\AuthorizationRequestListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionRequirementsListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SignInListener;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AuthorizationRequestListener::class)]
#[CoversClass(SignInListener::class)]
#[CoversClass(SessionRequirementsListener::class)]
final class RequiredAssuranceTest extends FunctionalTestCase
{
    private const string BASIC = 'urn:example:basic';

    private const string STRONG = 'urn:example:strong';

    public function testEverySignInAsksForTheRequiredLevel(): void
    {
        $this->boot(['elevation' => ['required_level' => 'basic']]);

        self::assertSame(self::BASIC.' '.self::STRONG, FakeOidcProvider::query($this->startSignIn())['acr_values']);
    }

    public function testASignInAtTheRequiredLevelCompletes(): void
    {
        $this->boot(['elevation' => ['required_level' => 'basic']]);
        $this->signIn(['acr' => self::BASIC]);

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertTrue($this->container()->has(SessionRequirementsListener::class));
    }

    public function testASignInBelowTheRequiredLevelFails(): void
    {
        $this->boot(['elevation' => ['required_level' => 'strong']]);

        $response = $this->signIn(['acr' => self::BASIC]);

        self::assertSame('http://localhost/', $response->headers->get('Location'));
        self::assertNull($this->securityToken());
        self::assertSame([], $this->events()->of(UserSignedIn::class));
        self::assertTrue($this->get('/private')->isRedirection());
    }

    public function testASignInWithoutAcrFails(): void
    {
        $this->boot(['elevation' => ['required_level' => 'basic']]);
        $this->signIn();

        self::assertNull($this->securityToken());
    }

    public function testWithoutRequirementNothingIsAskedNorChecked(): void
    {
        $this->boot();

        self::assertArrayNotHasKey('acr_values', FakeOidcProvider::query($this->startSignIn()));
        self::assertFalse($this->container()->has(SessionRequirementsListener::class));
    }
}

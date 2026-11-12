<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Event\ElevationRefused;
use Phil\IdentityClientBundle\Application\Event\ElevationRequested;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceChallengeListener;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceVoter;
use Phil\IdentityClientBundle\Infrastructure\Security\ElevationAttempts;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AssuranceVoter::class)]
#[CoversClass(AssuranceEvaluator::class)]
#[CoversClass(AssuranceChallengeListener::class)]
#[CoversClass(ElevationAttempts::class)]
#[CoversClass(AssuranceLevels::class)]
final class ElevationTest extends FunctionalTestCase
{
    private const string BASIC = 'urn:example:basic';

    private const string STRONG = 'urn:example:strong';

    public function testAWeakSignInIsSentBackToTheProviderForTheLevel(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);

        $location = (string) $this->get('/strong')->headers->get('Location');

        self::assertStringStartsWith(FakeOidcProvider::ISSUER.'/authorize', $location);
        $query = FakeOidcProvider::query($location);
        self::assertSame(self::STRONG, $query['acr_values']);
        self::assertSame('login', $query['prompt']);
        self::assertCount(1, $this->events()->of(ElevationRequested::class));
    }

    public function testASignInAtTheLevelIsGranted(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);

        $response = $this->finishSignIn((string) $this->get('/strong')->headers->get('Location'), ['acr' => self::STRONG]);
        self::assertSame('http://localhost/strong', $response->headers->get('Location'));

        self::assertSame(Response::HTTP_OK, $this->get('/strong')->getStatusCode());
        self::assertSame([], $this->events()->of(ElevationRefused::class));
    }

    public function testAProviderThatDeclinesDoesNotCauseALoop(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);

        $this->finishSignIn((string) $this->get('/strong')->headers->get('Location'), ['acr' => self::BASIC]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->get('/strong')->getStatusCode());
        $refused = $this->events()->of(ElevationRefused::class);
        self::assertCount(1, $refused);
        self::assertSame('strong', $refused[0]->level);
        self::assertSame('subject-1', $refused[0]->subject);
    }

    public function testADeclinedLevelCanBeAskedForAgainLater(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);
        $this->finishSignIn((string) $this->get('/strong')->headers->get('Location'), ['acr' => self::BASIC]);

        $this->clock()->modify('+60 seconds');

        self::assertStringStartsWith(FakeOidcProvider::ISSUER.'/authorize', (string) $this->get('/strong')->headers->get('Location'));
    }

    public function testAFailedElevationDoesNotCauseALoop(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);
        $authorization = (string) $this->get('/strong')->headers->get('Location');

        $this->finishSignIn($authorization, ['aud' => 'another-client']);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->get('/strong')->getStatusCode());
        self::assertCount(1, $this->events()->of(ElevationRefused::class));
    }

    public function testASignInStartedOutsideAnElevationForgetsTheOneAbandoned(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);
        $this->get('/strong');

        $this->signIn(['acr' => self::BASIC]);

        self::assertSame([], $this->events()->of(ElevationRefused::class));
    }

    public function testALevelBoundToAMaximumAgeAsksForAFreshAuthentication(): void
    {
        $this->boot();
        $this->signIn();
        self::assertSame(Response::HTTP_OK, $this->get('/fresh')->getStatusCode());

        $this->clock()->modify('+301 seconds');
        $location = (string) $this->get('/fresh')->headers->get('Location');

        self::assertStringStartsWith(FakeOidcProvider::ISSUER.'/authorize', $location);
        self::assertSame('login', FakeOidcProvider::query($location)['prompt']);
        self::assertArrayNotHasKey('acr_values', FakeOidcProvider::query($location));
    }

    public function testTheNativeRecentAttributeAsksForAFreshAuthentication(): void
    {
        $this->boot();
        $this->signIn();
        self::assertSame(Response::HTTP_OK, $this->get('/recent')->getStatusCode());

        $this->clock()->modify('+3 hours');

        self::assertStringStartsWith(FakeOidcProvider::ISSUER.'/authorize', (string) $this->get('/recent')->headers->get('Location'));
    }

    public function testAScriptReceivesAChallengeInsteadOfARedirect(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);

        $response = $this->get('/strong', ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            'Bearer error="insufficient_user_authentication", error_description="A stronger authentication is required.", acr_values="urn:example:strong"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function testAnElevationDoesNotPushTheCapBack(): void
    {
        $this->boot();
        $this->signIn(['acr' => self::BASIC]);

        $this->clock()->modify('+11 hours');
        $this->finishSignIn((string) $this->get('/strong')->headers->get('Location'), ['acr' => self::STRONG]);
        self::assertSame(Response::HTTP_OK, $this->get('/strong')->getStatusCode());

        $this->clock()->modify('+1 hour');

        self::assertTrue($this->get('/strong')->isRedirection());
    }
}

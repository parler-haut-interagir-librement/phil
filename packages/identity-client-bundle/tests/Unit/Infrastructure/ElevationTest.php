<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Event\ElevationRefused;
use Phil\IdentityClientBundle\Application\Event\ElevationRequested;
use Phil\IdentityClientBundle\Application\Event\ReturnToDiscarded;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceChallengeListener;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceVoter;
use Phil\IdentityClientBundle\Infrastructure\Security\AuthorizationRequestListener;
use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use Phil\IdentityClientBundle\Infrastructure\Security\ElevationAttempts;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\OidcAuthorizationRequestEvent;
use Throwable;

#[CoversClass(AssuranceEvaluator::class)]
#[CoversClass(AssuranceVoter::class)]
#[CoversClass(ElevationAttempts::class)]
#[CoversClass(AssuranceChallengeListener::class)]
#[CoversClass(AuthorizationRequestListener::class)]
final class ElevationTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private MockClock $clock;

    private AssuranceLevels $levels;

    private AssuranceEvaluator $evaluator;

    private SpyDispatcher $dispatcher;

    private ElevationAttempts $attempts;

    private Session $session;

    private Request $request;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('@'.self::NOW));
        $this->levels = new AssuranceLevels([new AssuranceLevel('basic', 'a'), new AssuranceLevel('strong', 'b'), new AssuranceLevel('fresh', 'a', 100), new AssuranceLevel('recent', null, 100)]);
        $this->evaluator = new AssuranceEvaluator($this->levels, $this->clock);
        $this->dispatcher = new SpyDispatcher();
        $this->attempts = new ElevationAttempts($this->levels, $this->evaluator, $this->dispatcher, $this->clock, 60);
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = new Request();
        $this->request->setSession($this->session);
    }

    public function testTheClassDecides(): void
    {
        self::assertTrue($this->evaluator->isSatisfied($this->level('strong'), $this->token('b')));
        self::assertFalse($this->evaluator->isSatisfied($this->level('strong'), $this->token('a')));
        self::assertFalse($this->evaluator->isSatisfied($this->level('strong'), $this->token(null)));
    }

    public function testFreshnessCountsFromTheLastProof(): void
    {
        self::assertTrue($this->evaluator->isSatisfied($this->level('fresh'), $this->token('a', ['pwd' => self::NOW - 500, 'otp' => self::NOW - 100])));
        self::assertFalse($this->evaluator->isSatisfied($this->level('fresh'), $this->token('a', ['pwd' => self::NOW - 101])));
    }

    public function testNoProofIsNotFresh(): void
    {
        self::assertFalse($this->evaluator->isSatisfied($this->level('recent'), $this->token(null)));
        self::assertFalse($this->evaluator->isSatisfied($this->level('recent'), new NullToken()));
    }

    public function testAClassOnlyLevelIgnoresTheAge(): void
    {
        self::assertTrue($this->evaluator->isSatisfied($this->level('basic'), $this->token('a', ['pwd' => 1])));
    }

    public function testASatisfiedLevelIsGranted(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter()->vote($this->token('b'), null, ['OIDC_ASSURANCE:strong'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
        self::assertFalse($this->request->attributes->has(AssuranceVoter::REQUEST_ATTRIBUTE));
    }

    public function testAnotherAttributeAbstains(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter()->vote($this->token('b'), null, ['ROLE_ADMIN']));
    }

    public function testAnUnmetLevelAsksForAReAuthentication(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token('a'), null, ['OIDC_ASSURANCE:strong'], $vote));
        self::assertSame('OIDC_ASSURANCE:strong', $vote->reAuthenticationAttribute);
        self::assertSame('strong', $this->request->attributes->get(AssuranceVoter::REQUEST_ATTRIBUTE));
        self::assertSame(['The authentication does not satisfy the "strong" assurance level.'], $vote->reasons);
    }

    public function testAVoteWithoutDetailStillDecides(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token('a'), null, ['OIDC_ASSURANCE:strong']));
    }

    public function testAnUnknownLevelIsDeniedWithoutAReAuthentication(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token('b'), null, ['OIDC_ASSURANCE:nope'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
    }

    public function testAnAnonymousTokenIsDeniedWithoutAReAuthentication(): void
    {
        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote(new NullToken(), null, ['OIDC_ASSURANCE:recent'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
    }

    public function testADeclinedLevelIsDeniedWithoutAReAuthentication(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->settle($this->session, $this->token('a'));

        $vote = new Vote();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter()->vote($this->token('a'), null, ['OIDC_ASSURANCE:strong'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
        self::assertFalse($this->request->attributes->has(AssuranceVoter::REQUEST_ATTRIBUTE));
    }

    public function testWithoutARequestNothingIsAsked(): void
    {
        $vote = new Vote();
        $voter = new AssuranceVoter($this->levels, $this->evaluator, $this->attempts, new RequestStack());

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token('a'), null, ['OIDC_ASSURANCE:strong'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
    }

    public function testWithoutASessionNothingIsAsked(): void
    {
        $vote = new Vote();
        $stack = new RequestStack([new Request()]);
        $voter = new AssuranceVoter($this->levels, $this->evaluator, $this->attempts, $stack);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token('a'), null, ['OIDC_ASSURANCE:strong'], $vote));
        self::assertNull($vote->reAuthenticationAttribute);
    }

    public function testASettledAttemptIsForgottenAndReported(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->settle($this->session, $this->token('a', [], 'sub-9'));

        self::assertEquals([new ElevationRefused('sub-9', 'strong')], $this->dispatcher->events);
        self::assertFalse($this->attempts->mayRetry($this->session, 'strong'));

        $this->attempts->settle($this->session, $this->token('a'));
        self::assertCount(1, $this->dispatcher->events);
    }

    public function testAReachedLevelIsNotDeclined(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->settle($this->session, $this->token('b'));

        self::assertSame([], $this->dispatcher->events);
        self::assertTrue($this->attempts->mayRetry($this->session, 'strong'));
    }

    public function testASignInWithoutAttemptIsNotDeclined(): void
    {
        $this->attempts->settle($this->session, $this->token('a'));

        self::assertSame([], $this->dispatcher->events);
    }

    public function testAForgottenAttemptIsNotDeclined(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->forget($this->session);
        $this->attempts->settle($this->session, $this->token('a'));
        $this->attempts->fail($this->session);

        self::assertSame([], $this->dispatcher->events);
    }

    public function testAFailedAttemptIsDeclinedOnce(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->fail($this->session);
        $this->attempts->fail($this->session);

        self::assertEquals([new ElevationRefused(null, 'strong')], $this->dispatcher->events);
        self::assertFalse($this->attempts->mayRetry($this->session, 'strong'));
    }

    public function testADeclinedLevelCanBeRetriedAfterTheWindow(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->fail($this->session);

        $this->clock->sleep(59);
        self::assertFalse($this->attempts->mayRetry($this->session, 'strong'));

        $this->clock->sleep(1);
        self::assertTrue($this->attempts->mayRetry($this->session, 'strong'));
    }

    public function testAnotherLevelIsNotHeldBack(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $this->attempts->fail($this->session);

        self::assertTrue($this->attempts->mayRetry($this->session, 'basic'));
    }

    public function testACorruptMarkerDoesNotHoldBack(): void
    {
        $this->session->set('_phil_identity_client.elevation.refused', 'x');
        self::assertTrue($this->attempts->mayRetry($this->session, 'strong'));

        $this->session->set('_phil_identity_client.elevation.refused', ['level' => 'strong', 'at' => 'x']);
        self::assertTrue($this->attempts->mayRetry($this->session, 'strong'));
    }

    public function testAScriptIsChallengedWithTheClassesAndTheAge(): void
    {
        $response = $this->challenge('OIDC_ASSURANCE:fresh', $this->scripted());

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            'Bearer error="insufficient_user_authentication", error_description="A stronger authentication is required.", acr_values="a b", max_age="100"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function testALevelWithoutClassOmitsTheParameter(): void
    {
        $response = $this->challenge('OIDC_ASSURANCE:recent', $this->scripted());

        self::assertNotNull($response);
        self::assertSame(
            'Bearer error="insufficient_user_authentication", error_description="A stronger authentication is required.", max_age="100"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function testALevelWithoutAgeOmitsTheParameter(): void
    {
        $response = $this->challenge('OIDC_ASSURANCE:strong', $this->scripted());

        self::assertNotNull($response);
        self::assertStringNotContainsString('max_age', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function testAnXmlHttpRequestIsChallenged(): void
    {
        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        self::assertNotNull($this->challenge('OIDC_ASSURANCE:strong', $request));
    }

    public function testAPageNavigationIsLeftToTheFirewall(): void
    {
        self::assertNull($this->challenge('OIDC_ASSURANCE:strong', new Request()));
    }

    public function testAnotherDenialIsLeftToTheFirewall(): void
    {
        self::assertNull($this->challenge('ROLE_ADMIN', $this->scripted()));
        self::assertNull($this->challenge('OIDC_ASSURANCE:nope', $this->scripted()));
    }

    public function testAnAnonymousVisitorIsLeftToTheFirewall(): void
    {
        self::assertNull($this->challenge('OIDC_ASSURANCE:strong', $this->scripted(), false));
    }

    public function testAWrappedDenialIsChallenged(): void
    {
        $denied = new AccessDeniedException();
        $denied->setAttributes(['OIDC_ASSURANCE:strong']);

        self::assertNotNull($this->respond(new RuntimeException('template', 0, $denied), $this->scripted(), true));
    }

    public function testAnotherExceptionIsLeftAlone(): void
    {
        self::assertNull($this->respond(new RuntimeException('x'), $this->scripted(), true));
    }

    public function testTheFirstAssuranceAttributeIsTheOneChallenged(): void
    {
        $denied = new AccessDeniedException();
        $denied->setAttributes(['ROLE_X', 7, 'OIDC_ASSURANCE:recent']);

        $response = $this->respond($denied, $this->scripted(), true);

        self::assertNotNull($response);
        self::assertStringContainsString('max_age="100"', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function testAValidReturnToIsKeptInTheSession(): void
    {
        $event = $this->authorize(['return_to' => '/account']);

        self::assertSame('/account', $this->session->get('_security.main.target_path'));
        self::assertArrayNotHasKey('return_to', $event->getParams());
        self::assertSame([], $this->dispatcher->events);
    }

    public function testARefusedReturnToClearsTheTarget(): void
    {
        $this->session->set('_security.main.target_path', '/earlier');

        $this->authorize(['return_to' => 'https://evil.example.test/']);

        self::assertFalse($this->session->has('_security.main.target_path'));
        self::assertEquals([new ReturnToDiscarded()], $this->dispatcher->events);
    }

    public function testAnArrayReturnToIsRefused(): void
    {
        $this->session->set('_security.main.target_path', '/earlier');

        $this->authorize(['return_to' => ['/a']]);

        self::assertFalse($this->session->has('_security.main.target_path'));
    }

    public function testNoReturnToLeavesTheTarget(): void
    {
        $this->session->set('_security.main.target_path', '/earlier');

        $this->authorize([]);

        self::assertSame('/earlier', $this->session->get('_security.main.target_path'));
        self::assertSame([], $this->dispatcher->events);
    }

    public function testAnElevationAsksForTheClassesAndIsPending(): void
    {
        $this->request->attributes->set(AssuranceVoter::REQUEST_ATTRIBUTE, 'basic');

        $event = $this->authorize([]);

        self::assertSame('a b', $event->getParams()['acr_values']);
        self::assertEquals([new ElevationRequested('basic')], $this->dispatcher->events);
        $this->attempts->fail($this->session);
        self::assertFalse($this->attempts->mayRetry($this->session, 'basic'));
    }

    public function testAnElevationWithoutClassSendsNoValues(): void
    {
        $this->request->attributes->set(AssuranceVoter::REQUEST_ATTRIBUTE, 'recent');

        self::assertArrayNotHasKey('acr_values', $this->authorize([])->getParams());
    }

    public function testOutsideAnElevationAPendingOneIsForgotten(): void
    {
        $this->attempts->begin($this->session, 'strong');

        $event = $this->authorize([]);

        self::assertSame(['existing' => 'kept'], $event->getParams());
        $this->attempts->fail($this->session);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testAnUnknownLevelIsNotAskedFor(): void
    {
        $this->request->attributes->set(AssuranceVoter::REQUEST_ATTRIBUTE, 'nope');

        self::assertSame(['existing' => 'kept'], $this->authorize([])->getParams());
    }

    private function level(string $name): AssuranceLevel
    {
        $level = $this->levels->get($name);
        self::assertNotNull($level);

        return $level;
    }

    /**
     * @param array<string, int> $proofs
     */
    private function token(?string $acr, array $proofs = [], string $subject = 'subject-1'): PostAuthenticationToken
    {
        $token = TokenFixture::token([TokenAttribute::ASSURANCE => $acr], $subject);
        $token->setAuthenticationProofs($proofs);

        return $token;
    }

    private function voter(): AssuranceVoter
    {
        $stack = new RequestStack([$this->request]);

        return new AssuranceVoter($this->levels, $this->evaluator, $this->attempts, $stack);
    }

    private function scripted(): Request
    {
        $request = new Request();
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function challenge(string $attribute, Request $request, bool $signedIn = true): ?Response
    {
        $denied = new AccessDeniedException();
        $denied->setAttributes([$attribute]);

        return $this->respond($denied, $request, $signedIn);
    }

    private function respond(Throwable $exception, Request $request, bool $signedIn): ?Response
    {
        $storage = new TokenStorage();
        if ($signedIn) {
            $storage->setToken($this->token('a'));
        }

        $event = new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        new AssuranceChallengeListener($this->levels, $storage)($event);

        if ($event->getResponse() instanceof Response) {
            self::assertTrue($event->isAllowingCustomResponseCode());
        }

        return $event->getResponse();
    }

    /**
     * @param array<string, mixed> $query
     */
    private function authorize(array $query): OidcAuthorizationRequestEvent
    {
        $this->request->query->replace($query);
        $event = new OidcAuthorizationRequestEvent($this->request, 'main', ['existing' => 'kept']);
        new AuthorizationRequestListener(new DeclaredOriginsReturnToPolicy([]), $this->levels, $this->attempts, $this->dispatcher, 'return_to')($event);

        return $event;
    }
}

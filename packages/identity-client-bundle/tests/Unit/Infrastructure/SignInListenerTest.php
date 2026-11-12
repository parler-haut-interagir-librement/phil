<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Event\ElevationRefused;
use Phil\IdentityClientBundle\Application\Event\UserSignedIn;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\ElevationAttempts;
use Phil\IdentityClientBundle\Infrastructure\Security\SignInListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[CoversClass(SignInListener::class)]
final class SignInListenerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private TokenStorage $storage;

    private SpyDispatcher $dispatcher;

    private Session $session;

    private RequestStack $stack;

    private ElevationAttempts $attempts;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('@'.self::NOW));
        $this->storage = new TokenStorage();
        $this->dispatcher = new SpyDispatcher();
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $this->stack = new RequestStack();
        $this->stack->push($request);

        $levels = new AssuranceLevels([new AssuranceLevel('strong', 'b')]);
        $this->attempts = new ElevationAttempts($levels, new AssuranceEvaluator($levels, $this->clock), $this->dispatcher, $this->clock, 60);
    }

    public function testAnotherAuthenticatorIsLeftAlone(): void
    {
        $token = TokenFixture::token(['keep' => 1, TokenAttribute::ACCESS_TOKEN => 'at']);

        $this->listener()->onTokenCreated(new AuthenticationTokenCreatedEvent($token, $this->passport(null)));

        self::assertSame(['keep' => 1, TokenAttribute::ACCESS_TOKEN => 'at'], $token->getAttributes());
        self::assertSame([], $this->dispatcher->events);
    }

    public function testAResponseWithoutIdTokenIsLeftAlone(): void
    {
        $token = TokenFixture::token();

        $this->listener()->onTokenCreated(new AuthenticationTokenCreatedEvent($token, $this->passport(['access_token' => 'x'])));

        self::assertFalse($token->hasAttribute(TokenAttribute::SESSION_STARTED_AT));
    }

    public function testTheConfiguredAttributesAreDiscarded(): void
    {
        $token = $this->signIn([], [TokenAttribute::ACCESS_TOKEN => 'at', 'keep' => 1], [TokenAttribute::ACCESS_TOKEN]);

        self::assertFalse($token->hasAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertSame(1, $token->getAttribute('keep'));
    }

    public function testTheSignInIsReported(): void
    {
        $this->signIn([], [TokenAttribute::PROVIDER_SESSION => 'sid', TokenAttribute::ASSURANCE => 'b']);

        self::assertEquals([new UserSignedIn('subject-1', 'sid', 'b')], $this->dispatcher->events);
    }

    public function testTheStartIsReadFromTheConfiguredClaim(): void
    {
        self::assertSame(self::NOW - 10, $this->started($this->signIn(['session_start' => self::NOW - 10, 'auth_time' => self::NOW - 5])));
    }

    public function testTheStartFallsBackOnAuthTime(): void
    {
        self::assertSame(self::NOW - 5, $this->started($this->signIn(['auth_time' => self::NOW - 5])));
    }

    public function testTheStartFallsBackOnTheSignIn(): void
    {
        self::assertSame(self::NOW, $this->started($this->signIn([])));
        self::assertSame(self::NOW, $this->started($this->signIn(['session_start' => 'yesterday'])));
    }

    public function testAStartInTheFutureIsTheSignIn(): void
    {
        self::assertSame(self::NOW, $this->started($this->signIn(['session_start' => self::NOW + 1000])));
    }

    public function testAnElevationKeepsTheEarlierStart(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::SESSION_STARTED_AT => self::NOW - 500, TokenAttribute::PROVIDER_SESSION => 'sid']));

        self::assertSame(self::NOW - 500, $this->started($this->signIn(['auth_time' => self::NOW], [TokenAttribute::PROVIDER_SESSION => 'sid'])));
    }

    public function testALaterPreviousStartDoesNotMoveItForward(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::SESSION_STARTED_AT => self::NOW - 5, TokenAttribute::PROVIDER_SESSION => 'sid']));

        self::assertSame(self::NOW - 500, $this->started($this->signIn(['auth_time' => self::NOW - 500], [TokenAttribute::PROVIDER_SESSION => 'sid'])));
    }

    public function testAPreviousTokenWithoutStartIsIgnored(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::PROVIDER_SESSION => 'sid']));

        self::assertSame(self::NOW - 1, $this->started($this->signIn(['auth_time' => self::NOW - 1], [TokenAttribute::PROVIDER_SESSION => 'sid'])));
    }

    public function testAnotherUserStartsAfresh(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::SESSION_STARTED_AT => self::NOW - 500, TokenAttribute::PROVIDER_SESSION => 'sid'], 'someone-else'));

        self::assertSame(self::NOW, $this->started($this->signIn([], [TokenAttribute::PROVIDER_SESSION => 'sid'])));
    }

    public function testAnotherProviderSessionStartsAfresh(): void
    {
        $this->storage->setToken(TokenFixture::token([TokenAttribute::SESSION_STARTED_AT => self::NOW - 500, TokenAttribute::PROVIDER_SESSION => 'old']));

        self::assertSame(self::NOW, $this->started($this->signIn([], [TokenAttribute::PROVIDER_SESSION => 'new'])));
    }

    public function testThePendingElevationIsSettled(): void
    {
        $this->attempts->begin($this->session, 'strong');

        $this->signIn([], [TokenAttribute::ASSURANCE => 'a']);

        self::assertInstanceOf(ElevationRefused::class, $this->dispatcher->events[0]);
    }

    public function testWithoutSessionTheSignInStillCompletes(): void
    {
        $this->stack->pop();
        $this->stack->push(new Request());

        $this->signIn([]);

        self::assertCount(1, $this->dispatcher->events);
    }

    public function testWithoutRequestTheSignInStillCompletes(): void
    {
        $this->stack->pop();

        $this->signIn([]);

        self::assertCount(1, $this->dispatcher->events);
    }

    public function testAFailedSignInFailsThePendingElevation(): void
    {
        $this->attempts->begin($this->session, 'strong');
        $request = $this->stack->getCurrentRequest();
        self::assertNotNull($request);

        $this->listener()->onLoginFailure(new LoginFailureEvent(new AuthenticationException(), self::createStub(AuthenticatorInterface::class), $request, null, 'main'));

        self::assertInstanceOf(ElevationRefused::class, $this->dispatcher->events[0]);
    }

    public function testAFailureWithoutSessionIsIgnored(): void
    {
        $this->listener()->onLoginFailure(new LoginFailureEvent(new AuthenticationException(), self::createStub(AuthenticatorInterface::class), new Request(), null, 'main'));

        self::assertSame([], $this->dispatcher->events);
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $attributes
     * @param list<string>         $discarded
     */
    private function signIn(array $claims, array $attributes = [], array $discarded = []): PostAuthenticationToken
    {
        $token = TokenFixture::token($attributes);
        $this->listener($discarded)->onTokenCreated(new AuthenticationTokenCreatedEvent($token, $this->passport(['id_token' => FakeOidcProvider::encode(['alg' => 'RS256'], $claims, 'sig')])));

        return $token;
    }

    private function started(AbstractToken $token): mixed
    {
        return $token->getAttribute(TokenAttribute::SESSION_STARTED_AT);
    }

    /**
     * @param array<string, mixed>|null $tokenData
     */
    private function passport(?array $tokenData): SelfValidatingPassport
    {
        $passport = new SelfValidatingPassport(new UserBadge('subject-1'));
        $passport->setAttribute('oidc_token_data', $tokenData);

        return $passport;
    }

    /**
     * @param list<string> $discarded
     */
    private function listener(array $discarded = []): SignInListener
    {
        return new SignInListener(new OidcIdToken($this->clock), $this->storage, $this->stack, $this->attempts, $this->dispatcher, $this->clock, 'session_start', $discarded);
    }
}

<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEndReason;
use Phil\IdentityClientBundle\Application\Exception\SessionLifetimeExceededException;
use Phil\IdentityClientBundle\Infrastructure\Security\EndedSessionListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionCapListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\Exception\OidcSessionEndedException;

#[CoversClass(SessionCapListener::class)]
#[CoversClass(EndedSessionListener::class)]
#[CoversClass(SessionLifetimeExceededException::class)]
#[CoversClass(TokenAttribute::class)]
final class SessionListenersTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    public function testASessionUnderTheCapStays(): void
    {
        self::assertFalse($this->check(self::NOW - 99)->isUserChanged());
    }

    public function testASessionAtTheCapEnds(): void
    {
        $event = $this->check(self::NOW - 100);

        self::assertTrue($event->isUserChanged());
        self::assertInstanceOf(SessionLifetimeExceededException::class, $event->getException());
    }

    public function testATokenOfAnotherAuthenticatorIsLeftAlone(): void
    {
        self::assertFalse($this->check(null)->isUserChanged());
    }

    public function testTheCapHasAMessage(): void
    {
        self::assertSame('The session has reached its maximum lifetime.', new SessionLifetimeExceededException()->getMessageKey());
    }

    public function testAnEndedProviderSessionInvalidatesTheLocalOne(): void
    {
        $this->assertSessionEnded(new OidcSessionEndedException(), LocalSessionEndReason::ProviderLogout);
    }

    public function testAnExceededCapInvalidatesTheLocalOne(): void
    {
        $this->assertSessionEnded(new SessionLifetimeExceededException(), LocalSessionEndReason::LifetimeExceeded);
    }

    public function testAnotherDeauthenticationLeavesTheSession(): void
    {
        [$session, $request] = $this->session();
        $session->set('kept', true);
        $dispatcher = new SpyDispatcher();

        new EndedSessionListener($dispatcher)(new TokenDeauthenticatedEvent(TokenFixture::token(), $request, 'x', [], new AuthenticationException()));
        new EndedSessionListener($dispatcher)(new TokenDeauthenticatedEvent(TokenFixture::token(), $request));

        self::assertTrue($session->get('kept'));
        self::assertSame([], $dispatcher->events);
    }

    public function testARequestWithoutSessionOnlyReports(): void
    {
        $dispatcher = new SpyDispatcher();

        new EndedSessionListener($dispatcher)(new TokenDeauthenticatedEvent(TokenFixture::token(), new Request(), 'x', [], new OidcSessionEndedException()));

        self::assertCount(1, $dispatcher->events);
    }

    public function testNonNumericAndEmptyAttributesReadAsAbsent(): void
    {
        $token = TokenFixture::token(['s' => '', 'n' => 'x', 'numeric' => '5', 't' => 'v']);

        self::assertNull(TokenAttribute::string($token, 's'));
        self::assertNull(TokenAttribute::string($token, 'missing'));
        self::assertSame('v', TokenAttribute::string($token, 't'));
        self::assertNull(TokenAttribute::integer($token, 'n'));
        self::assertNull(TokenAttribute::integer($token, 'missing'));
        self::assertSame(5, TokenAttribute::integer($token, 'numeric'));
    }

    private function assertSessionEnded(AuthenticationException $exception, LocalSessionEndReason $reason): void
    {
        [$session, $request] = $this->session();
        $session->set('kept', true);
        $dispatcher = new SpyDispatcher();
        $token = TokenFixture::token([TokenAttribute::PROVIDER_SESSION => 'sid-1']);

        new EndedSessionListener($dispatcher)(new TokenDeauthenticatedEvent($token, $request, 'x', [], $exception));

        self::assertNull($session->get('kept'));
        self::assertEquals([new LocalSessionEnded('subject-1', 'sid-1', $reason)], $dispatcher->events);
    }

    /**
     * @return array{Session, Request}
     */
    private function session(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        return [$session, $request];
    }

    private function check(?int $startedAt): CheckRefreshedUserEvent
    {
        $user = new InMemoryUser('subject-1', null);
        $token = TokenFixture::token(null === $startedAt ? [] : [TokenAttribute::SESSION_STARTED_AT => $startedAt]);
        $event = new CheckRefreshedUserEvent($token, $user, $user);
        new SessionCapListener(new MockClock(new DateTimeImmutable('@'.self::NOW)), 100)($event);

        return $event;
    }
}

<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\FailingSessions;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingMailer;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingTransactions;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Sessions::class)]
final class SessionsTest extends TestCase
{
    public function testSessionsOpenSeeAndClose(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $grants = new InMemoryGrantRepository();
        $mailer = new RecordingMailer();
        $sessions = new Sessions($store, $services->journal, $services->clock, $mailer, $grants, 60);
        $account = $services->account();

        $first = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        self::assertSame(43, \strlen($first->id()));
        self::assertFalse($first->isRisky());
        self::assertSame([IdentityEventType::SessionOpened->value, $account->id(), ['session' => $first->id(), 'risky' => false]], array_last($services->audit->records));
        self::assertSame([], $mailer->sent);

        $same = $sessions->open($account, 'Firefox on Linux', ['hwk']);
        self::assertFalse($same->isRisky());

        $risky = $sessions->open($account, 'Safari on iOS', ['pwd']);
        self::assertTrue($risky->isRisky());
        self::assertSame([IdentityEventType::SessionOpened->value, IdentityEventType::RiskySignIn->value], \array_slice($services->audit->actions(), -2));
        self::assertSame([['risky', $account->id(), 'Safari on iOS']], $mailer->sent);

        $seen = $first->lastSeenAt();
        $services->clock->modify('+59 seconds');
        self::assertEquals($first, $sessions->touch($first->id()));
        self::assertEquals($seen, $store->get($first->id())?->lastSeenAt());
        $services->clock->modify('+1 second');
        self::assertSame($first->id(), $sessions->touch($first->id(), $account->id())?->id());
        self::assertSame($services->clock->now()->getTimestamp(), $store->get($first->id())?->lastSeenAt()->getTimestamp());
        self::assertNull($sessions->touch($first->id(), 'someone else'));
        self::assertNull($sessions->touch('unknown'));

        $sessions->addMethods($first->id(), ['otp']);
        self::assertSame(['pwd', 'otp'], $store->get($first->id())->methods());
        $sessions->addMethods('unknown', ['otp']);

        $token = IssuedToken::issue('jti', TokenKind::Access, 'f', 'app', $account->id(), [], $first->id(), $services->clock->now(), [], $services->clock->now(), $services->clock->now()->modify('+1 hour'));
        $grants->saveToken($token);

        self::assertCount(3, $sessions->openFor($account));
        self::assertFalse($sessions->close($services->account('bob@example.com'), $first->id()));
        self::assertTrue($sessions->close($account, $first->id()));
        self::assertFalse($sessions->close($account, $first->id()));
        self::assertFalse($sessions->close($account, 'unknown'));
        self::assertTrue($store->get($first->id())->isClosed());
        self::assertTrue($grants->token('jti')?->isRevoked());
        self::assertNull($sessions->touch($first->id()));
        $sessions->addMethods($first->id(), ['hwk']);
        self::assertSame(['pwd', 'otp'], $store->get($first->id())->methods());
        self::assertSame([IdentityEventType::SessionClosed->value, $account->id(), ['session' => $first->id()]], array_last($services->audit->records));

        self::assertSame(1, $sessions->closeAll($account, $risky->id()));
        self::assertEquals([$risky], $sessions->openFor($account));
        self::assertSame(1, $sessions->closeAll($account));
        self::assertSame([], $sessions->openFor($account));
    }

    public function testTheTokensAreRevokedThenTheSessionClosedInOneTransaction(): void
    {
        $services = new Services();
        $store = new FailingSessions();
        $grants = new InMemoryGrantRepository();
        $transactions = new RecordingTransactions();
        $sessions = new Sessions($store, $services->journal, $services->clock, null, $grants, 300, $transactions);
        $account = $services->account();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $grants->saveToken(IssuedToken::issue('jti', TokenKind::Access, 'f', 'app', $account->id(), [], $session->id(), $services->clock->now(), [], $services->clock->now(), $services->clock->now()->modify('+1 hour')));

        $store->failNext = true;

        try {
            $sessions->close($account, $session->id());
            self::fail('The failure of the store was swallowed.');
        } catch (RuntimeException) {
        }

        self::assertSame(['begin'], $transactions->runs);
        self::assertFalse($store->get($session->id())?->isClosed(), 'A close cut short leaves the session open, to be closed again.');
        self::assertTrue($grants->token('jti')?->isRevoked());
        self::assertNotSame(IdentityEventType::SessionClosed->value, array_last($services->audit->actions()));

        self::assertTrue($sessions->close($account, $session->id()));
        self::assertSame(['begin', 'begin', 'commit'], $transactions->runs);
        self::assertTrue($store->get($session->id())->isClosed());
        self::assertSame(IdentityEventType::SessionClosed->value, array_last($services->audit->actions()));
    }

    public function testWithoutMailerNorProvider(): void
    {
        $services = new Services();
        $sessions = new Sessions(new InMemoryAccountSessionRepository(), $services->journal, $services->clock);
        $account = $services->account();
        $sessions->open($account, 'A', []);
        $session = $sessions->open($account, 'B', []);

        self::assertTrue($session->isRisky());
        self::assertTrue($sessions->close($account, $session->id()));
        $services->clock->modify('+299 seconds');
        $open = $sessions->openFor($account)[0];
        $seen = $open->lastSeenAt();
        $sessions->touch($open->id());
        self::assertEquals($seen, $sessions->openFor($account)[0]->lastSeenAt());
    }

    public function testAnIdleSessionClosesAtItsTimeoutWithItsTokens(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $grants = new InMemoryGrantRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock, null, $grants, 60, null, null, 600, null);
        $account = $services->account();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $grants->saveToken(IssuedToken::issue('jti', TokenKind::Access, 'f', 'app', $account->id(), [], $session->id(), $services->clock->now(), [], $services->clock->now(), $services->clock->now()->modify('+1 hour')));

        $services->clock->modify('+599 seconds');
        self::assertSame($session->id(), $sessions->touch($session->id(), $account->id())?->id(), 'One second before the timeout, the session goes on.');
        $services->clock->modify('+599 seconds');
        self::assertSame($session->id(), $sessions->touch($session->id())?->id(), 'A request moves the timeout.');
        self::assertFalse($grants->token('jti')?->isRevoked());

        $services->clock->modify('+600 seconds');
        self::assertNull($sessions->touch($session->id(), $account->id()));
        self::assertTrue($store->get($session->id())?->isClosed());
        self::assertSame($services->clock->now()->getTimestamp(), $store->get($session->id())->closedAt()?->getTimestamp());
        self::assertTrue($grants->token('jti')->isRevoked());
        self::assertSame([IdentityEventType::SessionClosed->value, $account->id(), ['session' => $session->id(), 'reason' => 'idle_timeout']], array_last($services->audit->records));
        self::assertNull($sessions->touch($session->id()));
    }

    public function testASessionClosesAtItsMaximumAgeHoweverActive(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock, null, null, 0, null, null, 7200, 3600);
        $account = $services->account();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);

        for ($minute = 1; $minute < 60; ++$minute) {
            $services->clock->modify('+60 seconds');
            self::assertNotNull($sessions->touch($session->id()));
        }

        $services->clock->modify('+59 seconds');
        self::assertNotNull($sessions->touch($session->id()), 'One second before its maximum age, the session goes on.');
        $services->clock->modify('+1 second');
        self::assertNull($sessions->touch($session->id()));
        self::assertTrue($store->get($session->id())?->isClosed());
        self::assertSame([IdentityEventType::SessionClosed->value, $account->id(), ['session' => $session->id(), 'reason' => 'max_age']], array_last($services->audit->records));
    }

    public function testTheMaximumAgeIsToldBeforeTheIdleTimeout(): void
    {
        $services = new Services();
        $sessions = new Sessions(new InMemoryAccountSessionRepository(), $services->journal, $services->clock, null, null, 300, null, null, 600, 600);
        $session = $sessions->open($services->account(), 'A', []);
        $services->clock->modify('+600 seconds');

        self::assertNull($sessions->touch($session->id()));
        self::assertSame(['session' => $session->id(), 'reason' => 'max_age'], array_last($services->audit->records)[2] ?? null);
    }

    public function testTheBoundsAreSecureByDefaultAndMayBeLeftOut(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock, null, null, 0);
        $account = $services->account();
        $idle = $sessions->open($account, 'A', []);
        $services->clock->modify('+7199 seconds');
        self::assertNotNull($sessions->touch($idle->id()));
        $services->clock->modify('+7200 seconds');
        self::assertNull($sessions->touch($idle->id()), 'Idle two hours, the session ends.');

        $active = $sessions->open($account, 'A', []);

        for ($hour = 1; $hour < 12; ++$hour) {
            $services->clock->modify('+3600 seconds');
            self::assertNotNull($sessions->touch($active->id()));
        }

        $services->clock->modify('+3599 seconds');
        self::assertNotNull($sessions->touch($active->id()));
        $services->clock->modify('+1 second');
        self::assertNull($sessions->touch($active->id()), 'Twelve hours after it opened, the session ends.');

        $unbound = new Sessions($store, $services->journal, $services->clock, null, null, 0, null, null, null, null);
        $kept = $unbound->open($account, 'A', []);
        $services->clock->modify('+400 days');
        self::assertSame($kept->id(), $unbound->touch($kept->id())?->id());
    }

    public function testTheListLeavesOutAndClosesTheExpiredSessions(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock, null, null, 0, null, null, 600, null);
        $account = $services->account();
        $old = $sessions->open($account, 'A', []);
        $services->clock->modify('+300 seconds');
        $fresh = $sessions->open($account, 'A', []);
        $services->clock->modify('+300 seconds');

        self::assertSame([$fresh->id()], array_map(static fn (AccountSession $session): string => $session->id(), $sessions->openFor($account)));
        self::assertTrue($store->get($old->id())?->isClosed());
        self::assertFalse($store->get($fresh->id())?->isClosed());
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function invalidBounds(): iterable
    {
        yield 'idle zero' => [0, null];
        yield 'idle negative' => [-1, 3600];
        yield 'age zero' => [null, 0];
        yield 'age negative' => [600, -1];
    }

    #[DataProvider('invalidBounds')]
    public function testABoundIsAtLeastOneSecond(?int $idle, ?int $age): void
    {
        $services = new Services();

        $this->expectException(InvalidValue::class);

        new Sessions(new InMemoryAccountSessionRepository(), $services->journal, $services->clock, null, null, 300, null, null, $idle, $age);
    }

    public function testABoundOfOneSecondIsAccepted(): void
    {
        $services = new Services();
        $sessions = new Sessions(new InMemoryAccountSessionRepository(), $services->journal, $services->clock, null, null, 0, null, null, 1, 1);
        $session = $sessions->open($services->account(), 'A', []);

        self::assertNotNull($sessions->touch($session->id()));
    }
}

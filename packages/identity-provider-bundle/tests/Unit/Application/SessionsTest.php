<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\FailingSessions;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingMailer;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingTransactions;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
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
}

<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Application;

use ArrayObject;
use Phil\IdentityProviderGoogle\Application\Event\SecurityEventReceived;
use Phil\IdentityProviderGoogle\Application\LinkedSessions;
use Phil\IdentityProviderGoogle\Application\Receipt;
use Phil\IdentityProviderGoogle\Application\ReplayGuard;
use Phil\IdentityProviderGoogle\Application\SecurityEventReceiver;
use Phil\IdentityProviderGoogle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogle\Domain\Risc\SecurityEventType;
use Phil\IdentityProviderGoogle\Tests\Fixture\FakeGoogle;
use Phil\IdentityProviderGoogle\Tests\Fixture\RiscHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityEventReceiver::class)]
#[CoversClass(Receipt::class)]
#[CoversClass(SecurityEventReceived::class)]
final class SecurityEventReceiverTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function closing(): iterable
    {
        yield 'sessions revoked' => ['risc/event-type/sessions-revoked'];
        yield 'tokens revoked' => ['oauth/event-type/tokens-revoked'];
        yield 'account disabled' => ['risc/event-type/account-disabled'];
        yield 'account purged' => ['risc/event-type/account-purged'];
        yield 'session revoked (CAEP)' => ['caep/event-type/session-revoked'];
    }

    #[DataProvider('closing')]
    public function testAnEventOfRevocationClosesTheSessionsOfTheLinkedAccount(string $type): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);

        $receipt = $harness->receiver->receive(RiscHarness::token($type));

        self::assertEquals(new Receipt(false, 1, 2), $receipt);
        self::assertSame([], $harness->openSessions());
        self::assertCount(1, $received);
        self::assertSame('google', $this->nth($received, 0)->provider);
        self::assertSame($harness->account->id(), $this->nth($received, 0)->accountId);
        self::assertSame(2, $this->nth($received, 0)->sessionsClosed);
        self::assertSame('https://schemas.openid.net/secevent/'.$type, $this->nth($received, 0)->type->value);
        self::assertFalse($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
    }

    public function testAnAccountDisabledAsHijackedIsHeldUntilEnabled(): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);

        $harness->receiver->receive(RiscHarness::token('risc/event-type/account-disabled', ['reason' => 'hijacking']));
        self::assertTrue($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
        self::assertSame('hijacking', $this->nth($received, 0)->reason);

        $receipt = $harness->receiver->receive(RiscHarness::token('risc/event-type/account-enabled'));
        self::assertFalse($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
        self::assertEquals(new Receipt(false, 1, 0), $receipt);
        self::assertSame(SecurityEventType::AccountEnabled, $this->nth($received, 1)->type);
        self::assertNull($this->nth($received, 1)->reason);
    }

    public function testAnAccountDisabledForAnotherReasonIsNotHeld(): void
    {
        $harness = new RiscHarness();

        $harness->receiver->receive(RiscHarness::token('risc/event-type/account-disabled', ['reason' => 'bulk-account']));

        self::assertFalse($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
        self::assertSame([], $harness->openSessions());
    }

    public function testASubjectLinkedToNoAccountIsHeldButClosesNothing(): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);

        $receipt = $harness->receiver->receive(RiscHarness::token('risc/event-type/account-disabled', ['reason' => 'hijacking'], subject: 'someone-else'));

        self::assertEquals(new Receipt(false, 1, 0), $receipt);
        self::assertTrue($harness->holds->isHeld('google', 'someone-else'));
        self::assertNull($this->nth($received, 0)->accountId);
        self::assertCount(2, $harness->openSessions());
    }

    public function testASubjectOfAnotherIssuerIsLeftAlone(): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);
        $token = RiscHarness::token('risc/event-type/account-disabled', ['reason' => 'hijacking', 'subject' => ['subject_type' => 'iss-sub', 'iss' => 'https://idp.example/', 'sub' => FakeGoogle::SUBJECT]]);

        self::assertEquals(new Receipt(false, 1, 0), $harness->receiver->receive($token));
        self::assertFalse($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
        self::assertNull($this->nth($received, 0)->accountId);
        self::assertCount(2, $harness->openSessions());

        $harness->holds->hold('google', FakeGoogle::SUBJECT);
        $harness->receiver->receive(RiscHarness::token('risc/event-type/account-enabled', ['subject' => ['subject_type' => 'iss-sub', 'iss' => 'https://idp.example/', 'sub' => FakeGoogle::SUBJECT]]));
        self::assertTrue($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
    }

    public function testAnEventThatClosesNothingKeepsTheSessions(): void
    {
        $harness = new RiscHarness();

        $receipt = $harness->receiver->receive(RiscHarness::token('risc/event-type/account-credential-change-required'));

        self::assertEquals(new Receipt(false, 1, 0), $receipt);
        self::assertCount(2, $harness->openSessions());
    }

    public function testAVerificationEventIsAcknowledged(): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);

        $receipt = $harness->receiver->receive(RiscHarness::token('risc/event-type/verification', ['subject' => null, 'state' => 'abc']));

        self::assertEquals(new Receipt(false, 1, 0), $receipt);
        self::assertSame(SecurityEventType::Verification, $this->nth($received, 0)->type);
        self::assertNull($this->nth($received, 0)->accountId);
    }

    public function testATokenReceivedTwiceIsActedOnOnce(): void
    {
        $harness = new RiscHarness();
        $received = $this->listen($harness);
        $token = RiscHarness::token('risc/event-type/account-enabled');

        $harness->receiver->receive($token);
        self::assertEquals(new Receipt(true), $harness->receiver->receive($token));
        self::assertCount(1, $received);
    }

    public function testATokenThatFailsIsNotRemembered(): void
    {
        $harness = new RiscHarness();
        $guard = new ReplayGuard($harness->cache);

        try {
            $harness->receiver->receive(RiscHarness::token('risc/event-type/account-enabled', claims: ['aud' => 'other', 'jti' => 'x']));
            self::fail('The token was received.');
        } catch (InvalidSecurityEventToken) {
        }

        self::assertFalse($guard->isKnown('https://accounts.google.com/', 'x'));
        $harness->receiver->receive(RiscHarness::token('risc/event-type/account-enabled', claims: ['jti' => 'y']));
        self::assertTrue($guard->isKnown('https://accounts.google.com/', 'y'));
    }

    public function testEventsOfOneTokenAreAddedUp(): void
    {
        $harness = new RiscHarness();
        $subject = ['subject_type' => 'iss-sub', 'iss' => 'https://accounts.google.com/', 'sub' => FakeGoogle::SUBJECT];
        $token = FakeGoogle::sign([
            'iss' => 'https://accounts.google.com/',
            'aud' => FakeGoogle::CLIENT_ID,
            'iat' => FakeGoogle::NOW,
            'jti' => 'two',
            'events' => [
                'https://schemas.openid.net/secevent/risc/event-type/sessions-revoked' => ['subject' => $subject],
                'https://schemas.openid.net/secevent/oauth/event-type/tokens-revoked' => ['subject' => $subject],
            ],
        ]);

        self::assertEquals(new Receipt(false, 2, 2), $harness->receiver->receive($token));
    }

    public function testWithoutHoldsNorDispatcherTheSessionsAreStillClosed(): void
    {
        $harness = new RiscHarness();
        $receiver = new SecurityEventReceiver($harness->reader, new ReplayGuard($harness->cache), new LinkedSessions($harness->identities, $harness->accounts), $harness->transmitter);

        self::assertEquals(new Receipt(false, 1, 0), $receiver->receive(RiscHarness::token('risc/event-type/account-disabled', ['reason' => 'hijacking'])));
        self::assertFalse($harness->holds->isHeld('google', FakeGoogle::SUBJECT));
        self::assertEquals(new Receipt(false, 1, 0), $receiver->receive(RiscHarness::token('risc/event-type/account-enabled')));
    }

    /**
     * @return ArrayObject<int, SecurityEventReceived>
     */
    private function listen(RiscHarness $harness): ArrayObject
    {
        /** @var ArrayObject<int, SecurityEventReceived> $received */
        $received = new ArrayObject();
        $harness->dispatcher->addListener(SecurityEventReceived::class, static function (SecurityEventReceived $event) use ($received): void {
            $received[] = $event;
        });

        return $received;
    }

    /**
     * @param ArrayObject<int, SecurityEventReceived> $received
     */
    private function nth(ArrayObject $received, int $index): SecurityEventReceived
    {
        $event = $received[$index] ?? null;
        self::assertInstanceOf(SecurityEventReceived::class, $event);

        return $event;
    }
}

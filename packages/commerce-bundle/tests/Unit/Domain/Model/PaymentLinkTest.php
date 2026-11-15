<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentLink::class)]
final class PaymentLinkTest extends TestCase
{
    public function testALinkOpensItsOrderUntilItExpires(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $until = new DateTimeImmutable('2026-11-12T10:00:00Z');
        $link = PaymentLink::create('2026-0001', hash('sha256', 't'), $at, $until);

        self::assertSame('2026-0001', $link->orderNumber);
        self::assertSame(hash('sha256', 't'), $link->tokenHash());
        self::assertSame($at, $link->createdAt);
        self::assertSame($until, $link->expiresAt);
        self::assertTrue($link->opensAt($at));
        self::assertTrue($link->opensAt(new DateTimeImmutable('2026-11-12T09:59:59Z')));
        self::assertFalse($link->opensAt($until));
        self::assertNull($link->revokedAt());
    }

    public function testARevokedLinkOpensNothingAndKeepsItsFirstRevocation(): void
    {
        $link = PaymentLink::create('2026-0001', hash('sha256', 't'), new DateTimeImmutable('2026-11-11T10:00:00Z'), new DateTimeImmutable('2026-11-12T10:00:00Z'));
        $first = new DateTimeImmutable('2026-11-11T11:00:00Z');

        $link->revoke($first);
        $link->revoke(new DateTimeImmutable('2026-11-11T12:00:00Z'));

        self::assertSame($first, $link->revokedAt());
        self::assertFalse($link->opensAt(new DateTimeImmutable('2026-11-11T10:30:00Z')));
    }

    public function testALinkExpiresAfterItIsCreated(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A payment link expires after it is created.');

        PaymentLink::create('2026-0001', hash('sha256', 't'), $at, $at);
    }

    public function testALinkIsStoredUnderAHash(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A payment link is stored under the hexadecimal SHA-256 of its token.');

        PaymentLink::create('2026-0001', 'token', new DateTimeImmutable('2026-11-11T10:00:00Z'), new DateTimeImmutable('2026-11-12T10:00:00Z'));
    }
}

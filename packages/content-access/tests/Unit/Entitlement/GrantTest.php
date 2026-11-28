<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit\Entitlement;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentAccess\Entitlement\ChainEntitlements;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\ContentAccess\Entitlement\InMemoryEntitlements;
use Phil\ContentAccess\Exception\InvalidGrant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Grant::class)]
#[CoversClass(InvalidGrant::class)]
#[CoversClass(InMemoryEntitlements::class)]
#[CoversClass(ChainEntitlements::class)]
final class GrantTest extends TestCase
{
    public function testAGrantHoldsFromItsStartToBeforeItsEnd(): void
    {
        $grant = new Grant('episodes:early', new DateTimeImmutable('2026-11-01T00:00:00+00:00'), new DateTimeImmutable('2026-12-01T00:00:00+00:00'), 'subscription');

        self::assertSame('episodes:early', $grant->feature);
        self::assertSame('subscription', $grant->source);
        self::assertFalse($grant->activeAt(new DateTimeImmutable('2026-10-31T23:59:59+00:00')));
        self::assertTrue($grant->activeAt(new DateTimeImmutable('2026-11-01T00:00:00+00:00')));
        self::assertTrue($grant->activeAt(new DateTimeImmutable('2026-11-30T23:59:59+00:00')));
        self::assertFalse($grant->activeAt(new DateTimeImmutable('2026-12-01T00:00:00+00:00')));
    }

    public function testAGrantWithoutEndHoldsForever(): void
    {
        $grant = new Grant('course/intro', new DateTimeImmutable('2026-11-01'));

        self::assertNull($grant->until);
        self::assertNull($grant->source);
        self::assertTrue($grant->activeAt(new DateTimeImmutable('2126-01-01')));
    }

    public function testDaysAfterTheStartAreCalendarDaysInItsZone(): void
    {
        $grant = new Grant('f', new DateTimeImmutable('2026-03-01T10:00:00', new DateTimeZone('America/Toronto')));

        self::assertSame('2026-03-11T10:00:00-04:00', $grant->daysAfterStart(10)->format(\DATE_ATOM));
        self::assertSame('2026-03-01T10:00:00-05:00', $grant->daysAfterStart(0)->format(\DATE_ATOM));
        self::assertSame('2026-03-01T10:00:00-05:00', $grant->daysAfterStart(-3)->format(\DATE_ATOM));
    }

    public function testAFeatureThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidGrant::class);
        $this->expectExceptionMessageIsOrContains('The grant\'s feature is not an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most.');

        new Grant('-early', new DateTimeImmutable());
    }

    public function testAFeatureOfOneHundredTwentyNineBytesIsRefused(): void
    {
        self::assertSame(128, \strlen(new Grant(str_repeat('a', 128), new DateTimeImmutable())->feature));

        $this->expectException(InvalidGrant::class);

        new Grant(str_repeat('a', 129), new DateTimeImmutable());
    }

    public function testAFeatureEndingWithANewlineIsRefused(): void
    {
        $this->expectException(InvalidGrant::class);

        new Grant("early\n", new DateTimeImmutable());
    }

    public function testASourceThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidGrant::class);
        $this->expectExceptionMessageIsOrContains("The grant's source is not");

        new Grant('early', new DateTimeImmutable(), null, 'a source');
    }

    public function testAnEndAtTheStartIsRefused(): void
    {
        $this->expectException(InvalidGrant::class);
        $this->expectExceptionMessageIsOrContains('The grant ends at or before its start.');

        new Grant('early', new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01'));
    }

    public function testInMemoryGrantsAddUpBySubject(): void
    {
        $a = new Grant('a', new DateTimeImmutable('2026-01-01'));
        $b = new Grant('b', new DateTimeImmutable('2026-01-01'));
        $c = new Grant('c', new DateTimeImmutable('2026-01-01'));

        $empty = new InMemoryEntitlements();
        $first = $empty->with('m1', $a);
        $both = $first->with('m1', $b)->with('m2', $c);

        self::assertSame([], $empty->grantsOf('m1'));
        self::assertSame([$a], $first->grantsOf('m1'));
        self::assertSame([$a, $b], $both->grantsOf('m1'));
        self::assertSame([$c], $both->grantsOf('m2'));
        self::assertSame([], $both->grantsOf('m3'));
    }

    public function testAChainReadsEverySourceInOrder(): void
    {
        $a = new Grant('a', new DateTimeImmutable('2026-01-01'));
        $b = new Grant('b', new DateTimeImmutable('2026-01-01'));
        $c = new Grant('c', new DateTimeImmutable('2026-01-01'));

        $chain = new ChainEntitlements(
            new InMemoryEntitlements(['m' => [$a, $b]]),
            new InMemoryEntitlements(),
            new InMemoryEntitlements(['m' => [$c]]),
        );

        self::assertSame([$a, $b, $c], $chain->grantsOf('m'));
        self::assertSame([], new ChainEntitlements()->grantsOf('m'));
    }

    public function testGrantsGivenByNameAreStillAListInOrder(): void
    {
        $first = new Grant('a', new DateTimeImmutable('2026-01-01'));
        $second = new Grant('b', new DateTimeImmutable('2026-01-02'));
        $third = new Grant('c', new DateTimeImmutable('2026-01-03'));

        $entitlements = new InMemoryEntitlements()->with('m', ...['x' => $first])->with('m', ...['y' => $second, 'z' => $third]);

        self::assertSame([$first, $second, $third], $entitlements->grantsOf('m'));
    }
}

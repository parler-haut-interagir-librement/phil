<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagMetadata::class)]
final class FlagMetadataTest extends TestCase
{
    public function testTheMetadataIsKeptTrimmed(): void
    {
        $metadata = FlagMetadata::of('  The one-page checkout. ', ' checkout-team ', FlagKind::Release, new DateTimeImmutable('2027-03-31'));

        self::assertSame('The one-page checkout.', $metadata->description);
        self::assertSame('checkout-team', $metadata->owner);
        self::assertSame(FlagKind::Release, $metadata->kind);
        self::assertSame('2027-03-31T00:00:00+00:00', $metadata->expiresOn?->format(\DATE_ATOM));
    }

    public function testNoMetadataIsAllNull(): void
    {
        $metadata = FlagMetadata::none();

        self::assertNull($metadata->description);
        self::assertNull($metadata->owner);
        self::assertNull($metadata->kind);
        self::assertNull($metadata->expiresOn);
        self::assertEquals($metadata, FlagMetadata::of());
    }

    public function testABlankTextIsNoText(): void
    {
        $metadata = FlagMetadata::of(' ', "\t");

        self::assertNull($metadata->description);
        self::assertNull($metadata->owner);
    }

    /**
     * Only the date counts, read in UTC: 23:30 in Montréal on the 30th is already the 31st in UTC.
     */
    public function testTheExpiryIsTheUtcDate(): void
    {
        $metadata = FlagMetadata::of(expiresOn: new DateTimeImmutable('2027-03-30T23:30:00-04:00'));

        self::assertSame('2027-03-31T00:00:00+00:00', $metadata->expiresOn?->format(\DATE_ATOM));
    }

    public function testAFlagIsExpiredFromItsExpiryDay(): void
    {
        $metadata = FlagMetadata::of(expiresOn: new DateTimeImmutable('2027-03-31'));

        self::assertFalse($metadata->isExpiredAt(new DateTimeImmutable('2027-03-30T23:59:59+00:00')));
        self::assertTrue($metadata->isExpiredAt(new DateTimeImmutable('2027-03-31T00:00:00+00:00')));
        self::assertTrue($metadata->isExpiredAt(new DateTimeImmutable('2028-01-01T00:00:00+00:00')));
    }

    public function testAFlagWithoutExpiryNeverExpires(): void
    {
        self::assertFalse(FlagMetadata::none()->isExpiredAt(new DateTimeImmutable('2999-01-01')));
    }

    public function testTheLengthsAreBounded(): void
    {
        $metadata = FlagMetadata::of(str_repeat('é', 500), str_repeat('o', 100));

        self::assertSame(500, mb_strlen((string) $metadata->description));
        self::assertSame(100, \strlen((string) $metadata->owner));
    }

    public function testADescriptionIsAtMostFiveHundredCharacters(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The description of a flag is at most 500 characters.');

        FlagMetadata::of(str_repeat('a', 501));
    }

    public function testAnOwnerIsAtMostOneHundredCharacters(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The owner of a flag is at most 100 characters.');

        FlagMetadata::of(owner: str_repeat('a', 101));
    }

    public function testATextMustBeValidUtf8(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The owner of a flag must be valid UTF-8.');

        FlagMetadata::of(owner: "\xC3");
    }
}

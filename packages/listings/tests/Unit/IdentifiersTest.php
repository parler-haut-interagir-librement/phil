<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\MemberId;
use Phil\Listings\ModerationReason;
use Phil\Listings\ResponseId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListingId::class)]
#[CoversClass(MemberId::class)]
#[CoversClass(ResponseId::class)]
#[CoversClass(ListingKind::class)]
#[CoversClass(ModerationReason::class)]
#[CoversClass(InvalidListing::class)]
final class IdentifiersTest extends TestCase
{
    #[TestWith(['01JC4Z9X8Y7W6V5T4S3R2Q1P0N'])]
    #[TestWith(['user:42'])]
    #[TestWith(['a.b_c-d'])]
    #[TestWith(['7'])]
    public function testAnIdentifierIsKept(string $value): void
    {
        self::assertSame($value, ListingId::of($value)->value);
        self::assertSame($value, MemberId::of($value)->value);
        self::assertSame($value, ResponseId::of($value)->value);
    }

    #[TestWith([''])]
    #[TestWith(['-a'])]
    #[TestWith(['a b'])]
    #[TestWith(["a\n"])]
    #[TestWith(['é'])]
    public function testAnInvalidIdentifierIsRefused(string $value): void
    {
        foreach ([ListingId::of(...), MemberId::of(...), ResponseId::of(...)] as $of) {
            try {
                $of($value);
                self::fail('An invalid identifier was kept.');
            } catch (InvalidListing $invalid) {
                self::assertSame('An identifier is 1 to 64 ASCII letters, digits, ".", "_", ":" or "-".', $invalid->getMessage());
            }
        }
    }

    public function testAnIdentifierIsAtMostSixtyFourBytes(): void
    {
        self::assertSame(64, ListingId::of(str_repeat('a', 64))->value |> \strlen(...));
        self::assertSame(64, MemberId::of(str_repeat('a', 64))->value |> \strlen(...));
        self::assertSame(64, ResponseId::of(str_repeat('a', 64))->value |> \strlen(...));

        foreach ([ListingId::of(...), MemberId::of(...), ResponseId::of(...)] as $of) {
            try {
                $of(str_repeat('a', 65));
                self::fail('A too long identifier was kept.');
            } catch (InvalidListing) {
            }
        }
    }

    public function testIdentifiersCompareByValue(): void
    {
        self::assertTrue(ListingId::of('a')->equals(ListingId::of('a')));
        self::assertFalse(ListingId::of('a')->equals(ListingId::of('A')));
        self::assertTrue(MemberId::of('a')->equals(MemberId::of('a')));
        self::assertFalse(MemberId::of('a')->equals(MemberId::of('b')));
        self::assertTrue(ResponseId::of('a')->equals(ResponseId::of('a')));
        self::assertFalse(ResponseId::of('a')->equals(ResponseId::of('b')));
        self::assertTrue(ListingKind::of('job')->equals(ListingKind::of('job')));
        self::assertFalse(ListingKind::of('job')->equals(ListingKind::of('classified')));
        self::assertTrue(ModerationReason::of('spam')->equals(ModerationReason::of('spam')));
        self::assertFalse(ModerationReason::of('spam')->equals(ModerationReason::of('unsafe')));
    }

    #[TestWith(['job'])]
    #[TestWith(['looking-for.group_2'])]
    public function testAKeyIsKept(string $value): void
    {
        self::assertSame($value, ListingKind::of($value)->value);
        self::assertSame($value, ModerationReason::of($value)->value);
        self::assertSame(64, \strlen(ListingKind::of(str_repeat('k', 64))->value));
        self::assertSame(64, \strlen(ModerationReason::of(str_repeat('k', 64))->value));
    }

    #[TestWith([''])]
    #[TestWith(['Job'])]
    #[TestWith(['job.'])]
    #[TestWith(['.job'])]
    #[TestWith(["job\n"])]
    #[TestWith(['kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk'])]
    public function testAnInvalidKeyIsRefused(string $value): void
    {
        try {
            ListingKind::of($value);
            self::fail('An invalid kind was kept.');
        } catch (InvalidListing $invalidListing) {
            self::assertSame('A kind is 1 to 64 lowercase ASCII segments separated by ".", "_" or "-".', $invalidListing->getMessage());
        }

        $this->expectExceptionObject(InvalidListing::because('A moderation reason is 1 to 64 lowercase ASCII segments separated by ".", "_" or "-".'));

        ModerationReason::of($value);
    }
}

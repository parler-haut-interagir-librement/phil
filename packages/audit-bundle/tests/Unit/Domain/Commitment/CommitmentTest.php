<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Commitment;

use Phil\AuditBundle\Domain\Commitment\Commitment;
use Phil\AuditBundle\Domain\Commitment\Salt;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Commitment::class)]
final class CommitmentTest extends TestCase
{
    /**
     * RFC 4231, test case 1: a twenty-byte key of `0x0b`, message "Hi There". The published vector is what
     * proves the construction really is `HMAC-SHA256(key = salt, message = value)`, with binary output, and
     * not a hashed concatenation or swapped arguments.
     */
    public function testItIsHmacSha256KeyedBySalt(): void
    {
        $commitment = Commitment::of(Salt::fromBytes(str_repeat("\x0b", 20)), 'Hi There');

        self::assertSame(
            hex2bin('b0344c61d8db38535ca8afceaf0bf12b881dc200c9833da726e9376c2e32cff7'),
            $commitment->bytes,
        );
    }

    public function testItMatchesTheValueItWasMadeFrom(): void
    {
        $salt = Salt::generate();

        self::assertTrue(Commitment::of($salt, 'philippe@example.com')->matches($salt, 'philippe@example.com'));
    }

    public function testItDoesNotMatchAnotherValue(): void
    {
        $salt = Salt::generate();

        self::assertFalse(Commitment::of($salt, 'philippe@example.com')->matches($salt, 'other@example.com'));
    }

    /**
     * Without the salt, the commitment no longer verifies: that is what makes deleting the salt an erasure
     * rather than a redaction a dictionary can undo.
     */
    public function testItDoesNotMatchUnderAnotherSalt(): void
    {
        $commitment = Commitment::of(Salt::generate(), 'philippe@example.com');

        self::assertFalse($commitment->matches(Salt::generate(), 'philippe@example.com'));
    }

    public function testAStoredCommitmentRoundTrips(): void
    {
        $salt = Salt::generate();
        $commitment = Commitment::of($salt, 'value');
        $stored = Commitment::fromBytes($commitment->bytes);

        self::assertTrue($stored->equals($commitment));
        self::assertTrue($stored->matches($salt, 'value'));
    }

    public function testTwoCommitmentsOnDifferentValuesAreNotEqual(): void
    {
        $salt = Salt::generate();

        self::assertFalse(Commitment::of($salt, 'a')->equals(Commitment::of($salt, 'b')));
    }

    public function testAStoredCommitmentTooShortIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A commitment is 32 bytes; this one is 31.');

        Commitment::fromBytes(str_repeat("\0", 31));
    }

    public function testAStoredCommitmentTooLongIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A commitment is 32 bytes; this one is 33.');

        Commitment::fromBytes(str_repeat("\0", 33));
    }

    public function testAStoredCommitmentOfExactlyThirtyTwoBytesIsAccepted(): void
    {
        self::assertSame(32, \strlen(Commitment::fromBytes(str_repeat("\1", 32))->bytes));
    }
}

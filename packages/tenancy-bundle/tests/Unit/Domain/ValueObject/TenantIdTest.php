<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantId::class)]
final class TenantIdTest extends TestCase
{
    public function testItKeepsTheReferenceAsGiven(): void
    {
        $tenant = TenantId::fromString('wholesale');

        self::assertSame('wholesale', $tenant->value);
        self::assertSame('wholesale', $tenant->toString());
        self::assertSame('wholesale', (string) $tenant);
    }

    public function testAReferenceOfTheMaximumLengthIsAccepted(): void
    {
        self::assertSame(255, \strlen(TenantId::fromString(str_repeat('a', 255))->value));
    }

    public function testAReferenceOneByteTooLongIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A tenant reference is at most 255 bytes; this one is 256.');

        TenantId::fromString(str_repeat('a', 256));
    }

    /**
     * The limit is in bytes, the unit of the column that stores it: 128 two-byte characters are 256 bytes.
     */
    public function testTheLimitCountsBytesNotCharacters(): void
    {
        $this->expectException(InvalidValue::class);

        TenantId::fromString(str_repeat('é', 128));
    }

    #[DataProvider('refused')]
    public function testItRefuses(string $value, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        TenantId::fromString($value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => ['', 'cannot be empty'];
        yield 'leading space' => [' retail', 'surrounding whitespace'];
        yield 'trailing newline' => ["retail\n", 'surrounding whitespace'];
        yield 'only spaces' => ['   ', 'surrounding whitespace'];
        yield 'inner NUL byte' => ["re\x00tail", 'control characters'];
        yield 'inner tab' => ["re\ttail", 'control characters'];
        yield 'DEL' => ["re\x7Ftail", 'control characters'];
        yield 'trailing no-break space' => ["retail\u{00A0}", 'surrounding whitespace'];
        yield 'leading ideographic space' => ["\u{3000}retail", 'surrounding whitespace'];
        yield 'inner C1 control (NEL)' => ["re\u{0085}tail", 'control characters'];
        yield 'invalid UTF-8' => ["\xff\xfe", 'valid UTF-8'];
        yield 'truncated UTF-8 sequence' => ["retail\xC3", 'valid UTF-8'];
    }

    /**
     * Read as bytes, the second byte of `ā` (`\xC4\x81`) would pass for a C1 control: the check reads characters.
     */
    public function testANonAsciiLetterIsNotAControlCharacter(): void
    {
        self::assertSame("caf\u{0101}", TenantId::fromString("caf\u{0101}")->value);
    }

    public function testAnInnerSpaceIsPartOfTheReference(): void
    {
        self::assertSame("north\u{00A0}shore", TenantId::fromString("north\u{00A0}shore")->value);
    }

    public function testTheSameReferenceIsEqual(): void
    {
        self::assertTrue(TenantId::fromString('retail')->equals(TenantId::fromString('retail')));
    }

    /**
     * Case folding would let a reference collide with another tenant's.
     */
    public function testReferencesThatDifferOnlyByCaseAreNotEqual(): void
    {
        self::assertFalse(TenantId::fromString('retail')->equals(TenantId::fromString('Retail')));
    }
}

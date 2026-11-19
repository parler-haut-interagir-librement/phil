<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use Phil\GuardianConsent\Exception\InvalidIdentifier;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\Internal\Identifier;
use Phil\GuardianConsent\MemberId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberId::class)]
#[CoversClass(GuardianId::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidIdentifier::class)]
final class IdentifierTest extends TestCase
{
    public function testAStableIdentifierIsKept(): void
    {
        $member = new MemberId('0193a6f2-7c1e-7d4b-9e2a-5f8c3b1d0e47');

        self::assertSame('0193a6f2-7c1e-7d4b-9e2a-5f8c3b1d0e47', $member->value);
        self::assertSame('0193a6f2-7c1e-7d4b-9e2a-5f8c3b1d0e47', (string) $member);
        self::assertTrue($member->equals(new MemberId('0193a6f2-7c1e-7d4b-9e2a-5f8c3b1d0e47')));
        self::assertFalse($member->equals(new MemberId('member-2')));
        self::assertSame('g-1', (string) new GuardianId('g-1'));
        self::assertTrue(new GuardianId('g-1')->equals(new GuardianId('g-1')));
        self::assertFalse(new GuardianId('g-1')->equals(new GuardianId('g-2')));
    }

    public function testTheLongestIdentifierIsKept(): void
    {
        self::assertSame(255, \strlen(new MemberId(str_repeat('a', 255))->value));
        self::assertSame(255, MemberId::MAX_LENGTH);
        self::assertSame(255, GuardianId::MAX_LENGTH);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => ['', 'The identifier of a member cannot be empty.'];
        yield 'too long' => [str_repeat('a', 256), 'The identifier of a member is at most 255 bytes.'];
        yield 'an e-mail address' => ['kid@example.org', 'The identifier of a member is a stable identifier, never an e-mail address: it holds no "@".'];
        yield 'a lone at sign' => ['@', 'The identifier of a member is a stable identifier, never an e-mail address: it holds no "@".'];
        yield 'a trailing newline' => ["member-1\n", 'The identifier of a member holds no whitespace and no control character.'];
        yield 'a space' => ['member 1', 'The identifier of a member holds no whitespace and no control character.'];
        yield 'a control character' => ["member\x7F", 'The identifier of a member holds no whitespace and no control character.'];
        yield 'a null byte' => ["member\x00", 'The identifier of a member holds no whitespace and no control character.'];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testAnIdentifierThatCannotBeKeptIsRefused(string $value, string $message): void
    {
        $this->expectException(InvalidIdentifier::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MemberId($value);
    }

    public function testAGuardianIdentifierNamesItsKind(): void
    {
        $this->expectExceptionMessageIsOrContains('The identifier of a guardian is a stable identifier, never an e-mail address: it holds no "@".');

        new GuardianId('parent@example.org');
    }
}

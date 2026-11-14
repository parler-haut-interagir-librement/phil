<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Domain;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifiers::class)]
#[CoversClass(InvalidNotification::class)]
final class IdentifiersTest extends TestCase
{
    #[TestWith(['a'])]
    #[TestWith(['comment.replied'])]
    #[TestWith(['event_7-b'])]
    public function testAKeyIsTaken(string $key): void
    {
        self::assertSame($key, Identifiers::key($key, 'type'));
    }

    public function testAKeyOfSixtyFourCharactersIsTaken(): void
    {
        $key = 'a'.str_repeat('b', 63);

        self::assertSame($key, Identifiers::key($key, 'type'));
    }

    #[TestWith([''])]
    #[TestWith(['Comment'])]
    #[TestWith(['1st'])]
    #[TestWith(['a b'])]
    #[TestWith(['a@b'])]
    #[TestWith(["a\n"])]
    #[TestWith(['.a'])]
    public function testAKeyOfAnotherShapeIsRefused(string $key): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('is not a key: lowercase letters, digits, "_", "." and "-", starting with a letter, 64 characters at most.');

        Identifiers::key($key, 'type');
    }

    public function testAKeyOfSixtyFiveCharactersIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The type "abbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb" is not a key');

        Identifiers::key('a'.str_repeat('b', 64), 'type');
    }

    #[TestWith(['42'])]
    #[TestWith(['user:42'])]
    #[TestWith(['0194f1c2-7a4b-7c3e-9d2f-1a2b3c4d5e6f'])]
    #[TestWith(['A_b.c'])]
    public function testAnIdentifierIsTaken(string $id): void
    {
        self::assertSame($id, Identifiers::id($id, 'recipient'));
    }

    public function testAnIdentifierOfOneHundredNinetyCharactersIsTaken(): void
    {
        $id = str_repeat('7', 190);

        self::assertSame($id, Identifiers::id($id, 'recipient'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIds(): iterable
    {
        yield 'empty' => [''];
        yield 'an address' => ['ada@example.test'];
        yield 'a space' => ['Ada Lovelace'];
        yield 'leading punctuation' => [':42'];
        yield 'a newline' => ["42\n"];
        yield 'too long' => [str_repeat('7', 191)];
    }

    #[DataProvider('badIds')]
    public function testAnIdentifierOfAnotherShapeIsRefused(string $id): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('is not an identifier: letters, digits, "_", ".", ":" and "-", 190 characters at most.');

        Identifiers::id($id, 'recipient');
    }

    public function testTheMessageNamesTheValueCutShort(): void
    {
        $this->expectExceptionMessageIsOrContains('The recipient "a'.str_repeat('é', 19).'" is not');

        Identifiers::id('a'.str_repeat('é', 30), 'recipient');
    }
}

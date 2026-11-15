<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GiftOptions::class)]
final class GiftOptionsTest extends TestCase
{
    public function testTheOptionsAreKeptAndStoredAsTheyAre(): void
    {
        $options = GiftOptions::of(true, true, "Happy birthday,\nAlice!", true);

        self::assertTrue($options->wrapped);
        self::assertTrue($options->recycledWrap);
        self::assertSame("Happy birthday,\nAlice!", $options->message);
        self::assertTrue($options->pricesHidden);
        self::assertSame(['wrapped' => true, 'recycled_wrap' => true, 'message' => "Happy birthday,\nAlice!", 'prices_hidden' => true], $options->toArray());
        self::assertEquals($options, GiftOptions::fromArray($options->toArray()));
    }

    public function testNothingIsChosenByDefault(): void
    {
        $options = GiftOptions::fromArray([]);

        self::assertFalse($options->wrapped);
        self::assertFalse($options->recycledWrap);
        self::assertNull($options->message);
        self::assertFalse($options->pricesHidden);
    }

    public function testTheLongestMessageIsAccepted(): void
    {
        self::assertSame(500, mb_strlen((string) GiftOptions::of(message: str_repeat('é', 500))->message));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedMessages(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ["  \n "];
        yield 'too long' => [str_repeat('a', 501)];
        yield 'a control character' => ["Hi\x07"];
        yield 'a tab' => ["Hi\tthere"];
        yield 'not UTF-8' => ["Caf\xE9"];
    }

    #[DataProvider('refusedMessages')]
    public function testAMessageIsTextOfAtMost500Characters(string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A gift message holds 1 to 500 characters of text, line breaks allowed.');

        GiftOptions::of(message: $message);
    }

    public function testARecycledWrapIsAWrapping(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A recycled wrap is a wrapping: the order is wrapped, or its wrap is not recycled.');

        GiftOptions::of(false, true);
    }
}

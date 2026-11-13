<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Domain;

use Phil\CommentsBundle\Domain\Exception\CommentsException;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Anchor::class)]
#[CoversClass(InvalidComment::class)]
final class AnchorTest extends TestCase
{
    public function testAPassageOfABlock(): void
    {
        $anchor = Anchor::of('b-1_x', 'items.0.content', 3, 'é😀c', 'ab', 'de');

        self::assertSame(['block' => 'b-1_x', 'field' => 'items.0.content', 'start' => 3, 'end' => 6, 'quote' => 'é😀c', 'prefix' => 'ab', 'suffix' => 'de'], $anchor->toArray());
        self::assertTrue($anchor->isIn('b-1_x', 'items.0.content'));
        self::assertFalse($anchor->isIn('b-1_x', 'text'));
        self::assertFalse($anchor->isIn('b2', 'items.0.content'));
    }

    public function testAPassageOfAPlainText(): void
    {
        $anchor = Anchor::of(null, null, 0, 'x');

        self::assertSame([null, null, 0, 1, '', ''], [$anchor->block, $anchor->field, $anchor->start, $anchor->end, $anchor->prefix, $anchor->suffix]);
        self::assertTrue($anchor->isIn(null, null));
        self::assertSame('summary', Anchor::of(null, 'summary', 0, 'x')->field);
    }

    public function testTheContextKeepsThe32CharactersNextToThePassage(): void
    {
        $anchor = Anchor::of(null, null, 0, 'x', '😀'.str_repeat('a', 32), str_repeat('b', 32).'😀');

        self::assertSame([str_repeat('a', 32), str_repeat('b', 32)], [$anchor->prefix, $anchor->suffix]);
        self::assertSame(['😀'.str_repeat('a', 31), str_repeat('b', 31).'😀'], [Anchor::of(null, null, 0, 'x', '😀'.str_repeat('a', 31), str_repeat('b', 31).'😀')->prefix, Anchor::of(null, null, 0, 'x', '', str_repeat('b', 31).'😀')->suffix]);
    }

    public function testTheLongestPassageAndPath(): void
    {
        $path = implode('.', array_fill(0, 16, str_repeat('a', 64)));

        self::assertSame(2000, Anchor::of(str_repeat('b', 64), $path, 0, str_repeat('😀', 2000))->end);
    }

    #[DataProvider('invalid')]
    public function testWhatAnAnchorCannotBeMadeOf(?string $block, ?string $field, int $start, string $quote, string $message, string $prefix = ''): void
    {
        try {
            Anchor::of($block, $field, $start, $quote, $prefix);
            self::fail($message);
        } catch (InvalidComment $invalidComment) {
            self::assertInstanceOf(CommentsException::class, $invalidComment);
            self::assertSame($message, $invalidComment->getMessage());
        }
    }

    /**
     * @return iterable<array{0: ?string, 1: ?string, 2: int, 3: string, 4: string, 5?: string}>
     */
    public static function invalid(): iterable
    {
        $block = 'A block is identified by 1 to 64 letters, digits, "_" or "-".';
        $field = 'A field is a path of 1 to 16 names of letters, digits or "_", separated by dots.';

        yield [str_repeat('b', 65), 'text', 0, 'x', $block];
        yield ['', 'text', 0, 'x', $block];
        yield ['b.1', 'text', 0, 'x', $block];
        yield ["b1\n", 'text', 0, 'x', $block];
        yield ['b1', '', 0, 'x', $field];
        yield ['b1', 'a..b', 0, 'x', $field];
        yield ['b1', 'a-b', 0, 'x', $field];
        yield ['b1', "text\n", 0, 'x', $field];
        yield ['b1', str_repeat('a', 65), 0, 'x', $field];
        yield ['b1', implode('.', array_fill(0, 17, 'a')), 0, 'x', $field];
        yield ['b1', null, 0, 'x', 'A passage of a block names the field it is in.'];
        yield ['b1', 'text', -1, 'x', 'A passage starts at an offset of zero or more characters.'];
        yield ['b1', 'text', 0, "\xFF", 'A passage and its context are UTF-8 texts.'];
        yield ['b1', 'text', 0, 'x', 'A passage and its context are UTF-8 texts.', "\xFF"];
        yield ['b1', 'text', 0, '', 'A passage is 1 to 2000 characters.'];
        yield ['b1', 'text', 0, str_repeat('a', 2001), 'A passage is 1 to 2000 characters.'];
    }

    public function testTheSuffixIsCheckedToo(): void
    {
        $this->expectExceptionMessageIsOrContains('A passage and its context are UTF-8 texts.');

        Anchor::of(null, null, 0, 'x', '', "\xFF");
    }
}

<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit;

use Override;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Item;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadListReader;
use Phil\Stockroom\Memory\InMemoryItems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadListReader::class)]
final class LoadListReaderTest extends TestCase
{
    private LoadListReader $reader;

    #[Override]
    protected function setUp(): void
    {
        $items = new InMemoryItems();

        foreach (['foam-sword', 'lantern', 'banner', '12'] as $code) {
            $items->save(new Item('id-'.$code, $code, ucfirst($code)));
        }

        $this->reader = new LoadListReader($items);
    }

    public function testItReadsEveryShapeOfLine(): void
    {
        $text = "# The tavern scene\n12 x foam-sword\r\n  4 lantern @ crate-3 \n\nbanner\r3×lantern\n2x banner@shelf\n12\n1 x 12\n";

        self::assertEquals([
            new LoadLine('id-foam-sword', 12),
            new LoadLine('id-lantern', 4, 'crate-3'),
            new LoadLine('id-banner'),
            new LoadLine('id-lantern', 3),
            new LoadLine('id-banner', 2, 'shelf'),
            new LoadLine('id-12'),
            new LoadLine('id-12'),
        ], $this->reader->read($text));
        self::assertSame([], $this->reader->read(''));
    }

    public function testItWritesWhatItReadsBack(): void
    {
        $lines = [new LoadLine('id-foam-sword', 12), new LoadLine('id-lantern', 4, 'crate-3'), new LoadLine('id-12')];
        $text = $this->reader->write($lines);

        self::assertSame("12 x foam-sword\n4 x lantern @ crate-3\n1 x 12\n", $text);
        self::assertEquals($lines, $this->reader->read($text));
        self::assertSame('', $this->reader->write([]));

        $this->expectExceptionMessageIsOrContains('There is no item "ghost".');
        $this->reader->write([new LoadLine('ghost')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badTexts(): iterable
    {
        yield 'a line that is not one' => ["banner\n\n3 x x x", 'Line 3 is not "quantity x code @ container": "3 x x x".'];
        yield 'an unknown item' => ['# list'."\n".'2 x ghost', 'Line 2 names the item "ghost", which does not exist.'];
        yield 'no quantity' => ['0 x banner', 'Line 1: A quantity is from 1 to 1000000, not 0.'];
        yield 'too many' => ['9999999 banner', 'Line 1: A quantity is from 1 to 1000000, not 9999999.'];
        yield 'an e-mail address' => ['1 x banner @ jo@example.com', 'Line 1 is not "quantity x code @ container": "1 x banner @ jo@example.com".'];
        yield 'a long line' => [str_repeat('é', 80), 'Line 1 is not "quantity x code @ container": "'.str_repeat('é', 59).'…".'];
        yield 'a line after a Windows line break' => ["banner\r\n3 x x x", 'Line 2 is not "quantity x code @ container": "3 x x x".'];
        yield 'not UTF-8' => ["banner\n\xff", 'A load list is UTF-8 text of at most 65536 bytes.'];
        yield 'too long' => [str_repeat('#', 65_537), 'A load list is UTF-8 text of at most 65536 bytes.'];
        yield 'a quantity with no separator' => ['3banner', 'Line 1 names the item "3banner", which does not exist.'];
    }

    #[DataProvider('badTexts')]
    public function testItRefusesWithTheNumberOfTheLine(string $text, string $message): void
    {
        $this->expectException(InvalidStock::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->reader->read($text);
    }

    public function testItReadsAsManyLinesAsAListHolds(): void
    {
        self::assertCount(500, $this->reader->read(str_repeat("banner\n# note\n", 500)));
        self::assertCount(1, $this->reader->read(str_repeat('#', 65_536 - 7)."\nbanner"));

        $this->expectExceptionMessageIsOrContains('A load list holds at most 500 lines.');
        $this->reader->read(str_repeat("banner\n", 501));
    }
}

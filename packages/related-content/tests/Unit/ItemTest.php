<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use DateTimeImmutable;
use Phil\RelatedContent\Exception\InvalidItem;
use Phil\RelatedContent\Exception\RelatedContentException;
use Phil\RelatedContent\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Item::class)]
#[CoversClass(InvalidItem::class)]
final class ItemTest extends TestCase
{
    public function testKeywordsAreTrimmedLoweredAndKeptOnce(): void
    {
        $date = new DateTimeImmutable('2026-11-02');
        $item = new Item('article:1', ['topics' => [' Board Games ', 'board games', '', '  ', 'ÉTÉ', '42']], $date, 'fr');

        self::assertSame(['topics' => ['board games', 'été', '42']], $item->keywords);
        self::assertSame('article:1', $item->id);
        self::assertSame($date, $item->date);
        self::assertSame('fr', $item->locale);
        self::assertSame([], $item->broader);
    }

    public function testBroaderTermsAreNormalizedAndReplacedByIndex(): void
    {
        $item = new Item('a', ['topics' => ['Epic']], broader: ['topics' => ['Fantasy']]);
        $expanded = $item->withBroader('topics', [' Fiction ', 'fiction'])->withBroader('genres', ['Story']);

        self::assertSame(['topics' => ['fantasy']], $item->broader);
        self::assertSame(['topics' => ['fiction'], 'genres' => ['story']], $expanded->broader);
        self::assertSame($item->keywords, $expanded->keywords);
        self::assertSame('a', $expanded->id);
    }

    public function testTheOtherFieldsSurviveWithBroader(): void
    {
        $date = new DateTimeImmutable('2026-01-01');
        $expanded = new Item('a', [], $date, 'en')->withBroader('topics', ['x']);

        self::assertSame($date, $expanded->date);
        self::assertSame('en', $expanded->locale);
    }

    public function testSetsOfKeywordsAndOfExpandedKeywords(): void
    {
        $item = new Item('a', ['topics' => ['epic', 'dark']], broader: ['topics' => ['fantasy', 'epic']]);

        self::assertSame(['epic' => true, 'dark' => true], $item->keywordSet('topics'));
        self::assertSame(['epic' => true, 'dark' => true, 'fantasy' => true], $item->expandedSet('topics'));
        self::assertSame([], $item->keywordSet('authors'));
        self::assertSame([], $item->expandedSet('authors'));
        self::assertSame(['fantasy' => true], new Item('b', broader: ['topics' => ['fantasy']])->expandedSet('topics'));
    }

    public function testLocalesAreSharedWhenEqualOrMissing(): void
    {
        $fr = new Item('a', locale: 'fr');
        $en = new Item('b', locale: 'en');
        $none = new Item('c');

        self::assertTrue($fr->sharesLocaleWith(new Item('d', locale: 'fr')));
        self::assertFalse($fr->sharesLocaleWith($en));
        self::assertTrue($fr->sharesLocaleWith($none));
        self::assertTrue($none->sharesLocaleWith($en));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'new line' => ["article:\n1"];
        yield 'nul' => ["a\x00"];
        yield 'delete' => ["a\x7F"];
    }

    #[DataProvider('malformedIdentifiers')]
    public function testAMalformedIdentifierIsRefused(string $id): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('is not an item identifier: 1 to 255 bytes, without control characters.');

        new Item($id);
    }

    public function testTheLongestIdentifierIsAccepted(): void
    {
        self::assertSame(255, \strlen(new Item(str_repeat('é', 127).'a')->id));
    }

    public function testTheRefusedIdentifierIsShownWithoutControlCharactersAndShortened(): void
    {
        try {
            new Item("x\ny".str_repeat('z', 300));
            self::fail('No exception.');
        } catch (InvalidItem $invalidItem) {
            self::assertInstanceOf(RelatedContentException::class, $invalidItem);
            self::assertStringStartsWith('"x?y'.str_repeat('z', 97).'" is not', $invalidItem->getMessage());
        }
    }

    public function testThirtyTwoIndicesAreCompared(): void
    {
        $keywords = array_fill_keys(array_map(static fn (int $i): string => 'i'.$i, range(1, 32)), ['x']);

        self::assertCount(32, new Item('a', $keywords)->keywords);
    }

    public function testMoreIndicesAreRefused(): void
    {
        $keywords = array_fill_keys(array_map(static fn (int $i): string => 'i'.$i, range(1, 33)), ['x']);

        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('An item holds 33 indices; at most 32 are compared.');

        new Item('a', broader: $keywords);
    }

    public function testTwoHundredKeywordsAreCompared(): void
    {
        $values = array_map(static fn (int $i): string => 'k'.$i, range(1, 200));

        self::assertCount(200, new Item('a', ['topics' => $values])->keywords['topics']);
    }

    public function testMoreKeywordsAreRefused(): void
    {
        $values = array_map(static fn (int $i): string => 'k'.$i, range(1, 201));

        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('An item holds 201 keywords in topics; at most 200 are compared.');

        new Item('a', ['topics' => $values]);
    }

    public function testTheIdentifierCheckIsPublicForTheOtherClassesOfTheLibrary(): void
    {
        Item::assertIdentifier('article:1');

        $this->expectException(InvalidItem::class);

        Item::assertIdentifier('');
    }

    public function testTheIdentifierShownInTheMessageIsCutAtOneHundredCharacters(): void
    {
        try {
            Item::assertIdentifier(str_repeat('é', 130));
            self::fail('A 260-byte identifier is too long.');
        } catch (InvalidItem $invalidItem) {
            self::assertStringContainsString('"'.str_repeat('é', 100).'" is not', $invalidItem->getMessage());
        }
    }
}

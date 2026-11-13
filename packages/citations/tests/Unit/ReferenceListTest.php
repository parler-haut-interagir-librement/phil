<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Exception\UnknownReference;
use Phil\Citations\Internal\Creators;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\Reference;
use Phil\Citations\ReferenceList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceList::class)]
#[CoversClass(Creators::class)]
#[CoversClass(UnknownReference::class)]
final class ReferenceListTest extends TestCase
{
    public function testDuplicatesByDoiAreOne(): void
    {
        $first = $this->reference('a', 'Title', doi: '10.1000/X');
        $list = new ReferenceList([
            $first,
            $this->reference('b', 'Other title', doi: 'https://doi.org/10.1000/x'),
            $this->reference('c', 'Third', doi: 'doi:10.1000/x'),
            $this->reference('d', 'Fourth', doi: 'http://dx.doi.org/10.1000/x'),
            $this->reference('e', 'Fifth', doi: '10.1000/y'),
        ]);

        self::assertSame($first, $list->get('b'));
        self::assertSame($first, $list->get('c'));
        self::assertSame($first, $list->get('d'));
        self::assertSame(['a', 'e'], array_map(static fn (Reference $reference): string => $reference->id, $list->all()));
        self::assertTrue($list->has('d'));
        self::assertFalse($list->has('z'));
    }

    public function testDuplicatesByTitleYearAndAuthorAreOne(): void
    {
        $list = new ReferenceList([
            $this->reference('a', 'Café: A Study', 2020, 'Émile'),
            $this->reference('b', 'cafe — a study', 2020, 'emile'),
            $this->reference('c', 'Café: A Study', 2021, 'Émile'),
            $this->reference('d', 'Café: A Study', 2020, 'Zola'),
            $this->reference('e', 'Café: A Study', 2020, editor: 'Émile'),
            $this->reference('f', '', 2020, 'Émile'),
            $this->reference('g', '', 2020, 'Émile'),
            $this->reference('h', '…', 2020, 'Émile'),
            $this->reference('i', '東京', 2020, 'Émile'),
            $this->reference('j', '東京 2', 2020, 'Émile'),
        ]);

        self::assertSame('a', $list->get('b')->id);
        self::assertSame('a', $list->get('e')->id);
        self::assertSame(['a', 'c', 'd', 'f', 'g', 'h', 'i', 'j'], array_map(static fn (Reference $reference): string => $reference->id, $list->all()));
    }

    public function testTheSameReferenceTwiceIsKeptOnce(): void
    {
        $list = new ReferenceList([$this->reference('a', 'T'), $this->reference('a', 'T'), $this->reference('b', 'T'), $this->reference('b', 'Changed')]);

        self::assertCount(1, $list->all());
        self::assertSame('a', $list->get('b')->id);
    }

    public function testTwoReferencesUnderOneIdAreRefused(): void
    {
        $this->expectExceptionObject(InvalidReference::because('two different references share the id "a"'));

        new ReferenceList([$this->reference('a', 'T'), $this->reference('a', 'U')]);
    }

    public function testAnUnknownIdIsRefused(): void
    {
        $this->expectExceptionObject(UnknownReference::id('z'));

        new ReferenceList([])->get('z');
    }

    public function testTheMessage(): void
    {
        self::assertSame('No reference has the id "z".', UnknownReference::id('z')->getMessage());
    }

    private function reference(string $id, string $title, ?int $year = null, ?string $author = null, ?string $editor = null, ?string $doi = null): Reference
    {
        return new Reference(
            $id,
            ItemType::Book,
            array_filter(['author' => null === $author ? [] : [new Name($author)], 'editor' => null === $editor ? [] : [new Name($editor)]], static fn (array $names): bool => [] !== $names),
            null === $year ? [] : ['issued' => CslDate::year($year)],
            array_filter(['title' => $title, 'DOI' => (string) $doi], static fn (string $value): bool => '' !== $value),
        );
    }
}

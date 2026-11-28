<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use Phil\RelatedContent\Item;
use Phil\RelatedContent\TaxonomyExpander;
use Phil\RelatedContent\Tests\Support\MemoryTaxonomy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxonomyExpander::class)]
final class TaxonomyExpanderTest extends TestCase
{
    public function testTheBroaderTermsOfTheNamedIndicesAreAdded(): void
    {
        $taxonomy = new MemoryTaxonomy(['epic' => ['fantasy', 'fiction'], 'ada' => ['people']]);
        $item = new Item('a', ['topics' => ['epic'], 'authors' => ['ada'], 'genres' => []]);

        $expanded = new TaxonomyExpander($taxonomy, ['topics', 'genres', 'series'])->expand($item);

        self::assertSame(['topics' => ['fantasy', 'fiction']], $expanded->broader);
        self::assertSame(['topics'], $taxonomy->asked);
    }

    public function testTheBroaderTermsAreBounded(): void
    {
        $parents = array_map(static fn (int $i): string => 'p'.$i, range(1, 250));
        $expanded = new TaxonomyExpander(new MemoryTaxonomy(['epic' => $parents]), ['topics'])->expand(new Item('a', ['topics' => ['epic']]));

        self::assertCount(200, $expanded->broader['topics']);
        self::assertSame('p200', $expanded->broader['topics'][199]);
    }
}

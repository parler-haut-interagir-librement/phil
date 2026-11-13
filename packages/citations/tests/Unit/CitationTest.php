<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\Citation;
use Phil\Citations\Cite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Citation::class)]
#[CoversClass(Cite::class)]
final class CitationTest extends TestCase
{
    public function testTheCitesAreAListWhateverTheArgumentsAreNamed(): void
    {
        $citation = new Citation(new Cite('a'), second: new Cite('b'), third: new Cite('c'));

        self::assertSame([0, 1, 2], array_keys($citation->cites));
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Cite $cite): string => $cite->id, $citation->cites));
    }
}

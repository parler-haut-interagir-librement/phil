<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\Deprecation;

use Phil\ApiContract\Deprecation\LinkHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkHeader::class)]
final class LinkHeaderTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string|null}>
     */
    public static function headers(): iterable
    {
        yield 'quoted' => [['<https://example.com/d>; rel="deprecation"'], 'https://example.com/d'];
        yield 'bare token' => [['<https://example.com/d>; rel=deprecation'], 'https://example.com/d'];
        yield 'case-insensitive' => [['<https://example.com/d>; REL="Deprecation"'], 'https://example.com/d'];
        yield 'one of several relations' => [['<https://example.com/d>; rel="help deprecation"'], 'https://example.com/d'];
        yield 'second entry of a line' => [['<https://example.com/n>; rel="next", <https://example.com/d>; type="text/html"; rel="deprecation"'], 'https://example.com/d'];
        yield 'second header line' => [['<https://example.com/n>; rel="next"', '<https://example.com/d>; rel="deprecation"'], 'https://example.com/d'];
        yield 'first match wins' => [['<https://example.com/a>; rel="deprecation", <https://example.com/b>; rel="deprecation"'], 'https://example.com/a'];
        yield 'another relation only' => [['<https://example.com/n>; rel="next"'], null];
        yield 'a relation that merely contains the word' => [['<https://example.com/n>; rel="deprecation-notes"'], null];
        yield 'no rel at all' => [['<https://example.com/d>; title="deprecation"'], null];
        yield 'a rel inside another quoted parameter' => [['<https://example.com/a>; title="x; rel=deprecation; y", <https://example.com/b>; rel=deprecation'], 'https://example.com/b'];
        yield 'only the first rel counts' => [['<https://example.com/a>; rel="next"; rel="deprecation"'], null];
        yield 'a rel after a quoted title' => [['<https://example.com/a>; title="a, b; c"; rel="deprecation"'], 'https://example.com/a'];
        yield 'empty target' => [['<>; rel="deprecation"'], null];
        yield 'garbage' => [['deprecation'], null];
        yield 'no header' => [[], null];
    }

    /**
     * @param list<string> $headers
     */
    #[DataProvider('headers')]
    public function testTheTargetOfTheRelationIsFound(array $headers, ?string $expected): void
    {
        self::assertSame($expected, LinkHeader::firstWithRelation($headers, 'deprecation'));
    }
}

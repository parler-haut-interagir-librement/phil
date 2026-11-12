<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\SearchBundle\Application\SearchActionSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchActionSchema::class)]
final class SearchActionSchemaTest extends TestCase
{
    public function testTheSiteSearchIsASearchAction(): void
    {
        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => 'https://example.com/',
            'name' => 'Le site',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://example.com/search?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ], SearchActionSchema::of('https://example.com/', 'https://example.com/search?q={search_term_string}', 'Le site'));
        self::assertArrayNotHasKey('name', SearchActionSchema::of('https://example.com/', 'https://example.com/s/{search_term_string}'));
        self::assertSame('{search_term_string}', SearchActionSchema::PLACEHOLDER);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The results URL holds {search_term_string} where the words go; "https://example.com/search?q=" does not.');
        SearchActionSchema::of('https://example.com/', 'https://example.com/search?q=');
    }
}

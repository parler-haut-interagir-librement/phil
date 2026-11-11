<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Extraction;
use Phil\StructuredData\Graph;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLdReader::class)]
#[CoversClass(Extraction::class)]
#[CoversClass(InvalidStructuredData::class)]
final class JsonLdReaderTest extends TestCase
{
    public function testThePageGivesBackTheGraphItPrints(): void
    {
        $graph = Graph::of(
            Node::of('Organization', ['name' => 'Le Gnome </script>'], 'https://example.com/#org'),
            Node::of('WebPage', ['url' => 'https://example.com/', 'publisher' => Node::of('Organization', [], 'https://example.com/#org')]),
        )->withLanguage('fr-CA');

        $extraction = JsonLdReader::fromHtml('<!doctype html><html><head><title>t</title>'.JsonLd::script($graph).'</head><body></body></html>');

        self::assertSame(1, $extraction->blocks);
        self::assertSame([], $extraction->errors);
        self::assertSame($graph->toArray(), $extraction->graph->toArray());
    }

    public function testEveryElementIsReadAndMergedAndTheBadOnesSkipped(): void
    {
        $html = <<<'HTML'
            <html><body>
            <script>var first = 1;</script>
            <script type="application/ld+json">{"@context": "https://schema.org", "@type": "Organization", "@id": "urn:org", "name": "A"}</script>
            <script type=" Application/LD+JSON ">[{"@type": "Organization", "@id": "urn:org", "url": "https://a.example"}, {"@type": "Person", "name": "P"}]</script>
            <script type="application/ld+json">{not json</script>
            <script type="application/ld+json">"text"</script>
            <script type="application/ld+json">{"@graph": {"@type": "Thing"}}</script>
            <script type="application/ld+json">[["nested"]]</script>
            <script type="application/ld+json">[3]</script>
            <script>var x = {"@type": "Thing"};</script>
            <script type="text/javascript">{"@type": "Thing"}</script>
            </body></html>
            HTML;

        $extraction = JsonLdReader::fromHtml($html);

        self::assertSame(7, $extraction->blocks);
        self::assertSame([
            ['@type' => 'Organization', '@id' => 'urn:org', 'name' => 'A', 'url' => 'https://a.example'],
            ['@type' => 'Person', 'name' => 'P'],
        ], $extraction->graph->toArray()['@graph']);
        // PHP words the error of its JSON parser, and says where it stands since 8.6.
        self::assertStringStartsWith('JSON-LD element 3: The JSON cannot be read: Syntax error', $extraction->errors[0]);
        self::assertSame([
            'JSON-LD element 4: A JSON-LD document is a node, a list of nodes or a map with a @graph.',
            'JSON-LD element 5: The @graph of a JSON-LD document is a list of nodes.',
            'JSON-LD element 6: A node of a JSON-LD document is a map.',
            'JSON-LD element 7: A node of a JSON-LD document is a map.',
        ], \array_slice($extraction->errors, 1));
    }

    public function testAPageIsReadForSixtyFourElementsAtMost(): void
    {
        $html = str_repeat('<script type="application/ld+json">{"@type": "Thing"}</script>', JsonLdReader::MAX_BLOCKS + 2);

        $extraction = JsonLdReader::fromHtml($html);

        self::assertSame(64, $extraction->blocks);
        self::assertCount(64, $extraction->graph);
        self::assertSame(['A page is read for 64 JSON-LD elements at most; the others are skipped.'], $extraction->errors);
        self::assertSame([], JsonLdReader::fromHtml(str_repeat('<script type="application/ld+json">{"@type": "Thing"}</script>', 64))->errors);
    }

    public function testAPageIsFiveMegabytesAtMost(): void
    {
        self::assertSame(0, JsonLdReader::fromHtml(str_repeat(' ', JsonLdReader::MAX_HTML_BYTES))->blocks);

        $this->expectExceptionObject(InvalidStructuredData::because('A page read for its structured data is 5000000 bytes at most.'));
        JsonLdReader::fromHtml(str_repeat(' ', JsonLdReader::MAX_HTML_BYTES + 1));
    }

    /**
     * @return iterable<string, array{string, list<array<string, mixed>>, ?string}>
     */
    public static function documents(): iterable
    {
        yield 'a node' => ['{"@type": "Thing", "name": "a"}', [['@type' => 'Thing', 'name' => 'a']], null];
        yield 'a list' => ['[{"@type": "Thing"}, {"@type": "Person"}]', [['@type' => 'Thing'], ['@type' => 'Person']], null];
        yield 'a graph' => ['{"@context": {"@vocab": "https://schema.org/", "@language": "fr"}, "@graph": [{"@type": "Thing"}]}', [['@type' => 'Thing']], 'fr'];
        yield 'a context list' => ['{"@context": ["https://schema.org", {"@language": "en-GB"}], "@type": "Thing"}', [['@type' => 'Thing']], 'en-GB'];
        yield 'a context without a language' => ['{"@context": ["https://schema.org", {"@language": 3}], "@type": "Thing"}', [['@type' => 'Thing']], null];
        yield 'a big number' => ['{"@type": "Thing", "n": 123456789012345678901234567890}', [['@type' => 'Thing', 'n' => '123456789012345678901234567890']], null];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    #[DataProvider('documents')]
    public function testAJsonLdDocumentIsANodeAListOrAGraph(string $json, array $nodes, ?string $language): void
    {
        $graph = JsonLdReader::fromJson($json);

        self::assertSame($nodes, $graph->toArray()['@graph']);
        self::assertSame($language, $graph->language);
    }

    public function testJsonNestsSixtyFourLevelsAtMost(): void
    {
        self::assertCount(1, JsonLdReader::fromJson('{"@type": "Thing", "x": '.str_repeat('{"a": ', 31).'1'.str_repeat('}', 31).'}'));

        try {
            JsonLdReader::fromJson(str_repeat('[', 65).str_repeat(']', 65));
            self::fail('JSON 65 levels deep is refused.');
        } catch (InvalidStructuredData $invalidStructuredData) {
            self::assertStringStartsWith('The JSON cannot be read: Maximum stack depth exceeded', $invalidStructuredData->getMessage());
        }
    }
}

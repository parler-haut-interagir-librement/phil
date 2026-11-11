<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Graph;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Graph::class)]
#[CoversClass(JsonLd::class)]
#[CoversClass(InvalidStructuredData::class)]
final class GraphTest extends TestCase
{
    public function testNodesOfOneIdMergeWhereTheFirstStands(): void
    {
        $graph = Graph::of(
            Node::of('Organization', ['name' => 'Le Gnome', 'url' => 'https://example.com'], 'https://example.com/#org'),
            Node::of('WebSite', ['name' => 'Site']),
            Node::of('Organization', ['telephone' => '+15145550100', 'name' => 'Le Gnome Archiviste'], 'https://example.com/#org'),
            Node::of('WebSite', ['name' => 'Site']),
        );

        self::assertCount(3, $graph);
        self::assertSame([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'Organization', '@id' => 'https://example.com/#org', 'name' => 'Le Gnome Archiviste', 'url' => 'https://example.com', 'telephone' => '+15145550100'],
                ['@type' => 'WebSite', 'name' => 'Site'],
                ['@type' => 'WebSite', 'name' => 'Site'],
            ],
        ], $graph->toArray());
        self::assertSame('+15145550100', $graph->get('https://example.com/#org')?->get('telephone'));
        self::assertNull($graph->get('https://example.com/#none'));
        self::assertFalse($graph->isEmpty());
        self::assertTrue(Graph::of()->isEmpty());
    }

    public function testANestedNodeWithAnIdRisesToTheTop(): void
    {
        $organization = Node::of('Organization', ['name' => 'Le Gnome'], 'https://example.com/#org');
        $page = Node::of('WebPage', [
            'url' => 'https://example.com/a',
            'publisher' => $organization,
            'author' => [Node::of('Person', ['name' => 'Ada', 'worksFor' => $organization->with('url', 'https://example.com')]), 'Anonymous'],
            'about' => Node::of('Thing', ['name' => 'plain']),
        ], 'https://example.com/a#webpage');

        $graph = Graph::of($page);

        self::assertSame([
            ['@type' => 'Organization', '@id' => 'https://example.com/#org', 'name' => 'Le Gnome', 'url' => 'https://example.com'],
            [
                '@type' => 'WebPage',
                '@id' => 'https://example.com/a#webpage',
                'url' => 'https://example.com/a',
                'publisher' => ['@id' => 'https://example.com/#org'],
                'author' => [['@type' => 'Person', 'name' => 'Ada', 'worksFor' => ['@id' => 'https://example.com/#org']], 'Anonymous'],
                'about' => ['@type' => 'Thing', 'name' => 'plain'],
            ],
        ], $graph->toArray()['@graph']);
        self::assertSame($graph->get('https://example.com/#org'), $graph->resolve(Reference::to('https://example.com/#org')));
        self::assertNull($graph->resolve(Reference::to('https://example.com/#none')));
        self::assertSame($organization, $graph->resolve($organization));
    }

    public function testAnIdentifiedNodeNestedInAnotherRisesWithIt(): void
    {
        $logo = Node::of('ImageObject', ['contentUrl' => 'https://example.com/logo.png'], 'https://example.com/#logo');
        $graph = Graph::of(Node::of('WebPage', ['publisher' => Node::of('Organization', ['name' => 'A', 'logo' => $logo], 'https://example.com/#org')]));

        self::assertSame([
            ['@type' => 'ImageObject', '@id' => 'https://example.com/#logo', 'contentUrl' => 'https://example.com/logo.png'],
            ['@type' => 'Organization', '@id' => 'https://example.com/#org', 'name' => 'A', 'logo' => ['@id' => 'https://example.com/#logo']],
            ['@type' => 'WebPage', 'publisher' => ['@id' => 'https://example.com/#org']],
        ], $graph->toArray()['@graph']);
    }

    public function testGraphsMergeAndKeepTheirLanguage(): void
    {
        $first = Graph::of(Node::of('Thing', ['name' => 'a'], 'urn:a'))->withLanguage('fr_CA');
        $second = Graph::of(Node::of('Thing', ['description' => 'b'], 'urn:a'), Node::of('Thing', ['name' => 'c']))->withLanguage('en');

        $merged = $first->merge($second);
        self::assertSame('fr-CA', $merged->language);
        self::assertSame([
            '@context' => ['https://schema.org', ['@language' => 'fr-CA']],
            '@graph' => [['@type' => 'Thing', '@id' => 'urn:a', 'name' => 'a', 'description' => 'b'], ['@type' => 'Thing', 'name' => 'c']],
        ], $merged->toArray());
        self::assertSame('en', Graph::of()->merge($second)->language);
        self::assertNull($first->withLanguage(null)->language);
        self::assertNull(Graph::of()->language);
        self::assertCount(1, $first);
        self::assertSame(['urn:a'], array_map(static fn (Node $node): ?string => $node->id, $first->nodes()));
    }

    public function testAGraphHoldsTenThousandNodesAtMost(): void
    {
        $nodes = [];

        for ($index = 0; $index < Graph::MAX_NODES; ++$index) {
            $nodes[] = Node::of('Thing', ['position' => $index]);
        }

        $graph = Graph::of(...$nodes);
        self::assertCount(10_000, $graph);

        $this->expectExceptionObject(InvalidStructuredData::because('A graph holds 10000 nodes at most.'));
        $graph->with(Node::of('Thing'));
    }

    public function testANodeOfAKnownIdStillMergesInAFullGraph(): void
    {
        $nodes = [Node::of('Thing', ['name' => 'a'], 'urn:a')];

        for ($index = 1; $index < Graph::MAX_NODES; ++$index) {
            $nodes[] = Node::of('Thing', ['position' => $index]);
        }

        $graph = Graph::of(...$nodes)->with(Node::of('Thing', ['url' => 'https://example.com'], 'urn:a'));

        self::assertCount(10_000, $graph);
        self::assertSame('https://example.com', $graph->get('urn:a')?->get('url'));
    }

    public function testTheScriptElementCannotBeClosedByItsData(): void
    {
        $graph = Graph::of(Node::of('Thing', [
            'name' => '</script><script>alert(1)</script>',
            'description' => "<!-- a & b's \u{2028}é/ \xff",
            'ratio' => 2.0,
        ]));

        $json = '{"@context":"https://schema.org","@graph":[{"@type":"Thing","name":"\u003C/script\u003E\u003Cscript\u003Ealert(1)\u003C/script\u003E","description":"\u003C!-- a \u0026 b\'s \\u2028é/ '."\u{fffd}".'","ratio":2.0}]}';

        self::assertSame($json, JsonLd::encode($graph));
        self::assertSame('<script type="application/ld+json">'.$json.'</script>', JsonLd::script($graph));
        self::assertSame('<script type="application/ld+json" nonce="r4nd+/0m_-=">'.$json.'</script>', JsonLd::script($graph, 'r4nd+/0m_-='));
        self::assertStringContainsString("\n    \"@context\": \"https://schema.org\",\n", JsonLd::encode($graph, pretty: true));
        self::assertSame('', JsonLd::script(Graph::of()));
        self::assertSame('', JsonLd::script(Graph::of(), str_repeat('a', 256)));
    }

    public function testABadNonceIsRefused(): void
    {
        foreach (['"><script>', '', str_repeat('a', 257), "abc\n"] as $nonce) {
            try {
                JsonLd::script(Graph::of(), $nonce);
                self::fail(\sprintf('The nonce "%s" is refused.', $nonce));
            } catch (InvalidStructuredData $invalid) {
                self::assertSame('A nonce is base64 text of 1 to 256 characters.', $invalid->getMessage());
            }
        }
    }
}

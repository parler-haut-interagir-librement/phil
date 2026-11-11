<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use Dom\HTMLDocument;
use JsonException;
use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * The JSON-LD of a page read back: its `<script type="application/ld+json">` elements, each a node, a list of nodes or
 * a document with a `@graph`, merged into one graph. It reads what pages hold, written by anyone: a page is 5 MB at
 * most, 64 elements are read, JSON nests 64 levels deep and nodes 32, and a graph holds 10,000 nodes.
 */
final class JsonLdReader
{
    public const int MAX_HTML_BYTES = 5_000_000;

    public const int MAX_BLOCKS = 64;

    public const int MAX_JSON_DEPTH = 64;

    /**
     * Every JSON-LD element of the page; an element that is not a schema.org graph is skipped, with the reason.
     *
     * @throws InvalidStructuredData when the page is larger than 5 MB
     */
    public static function fromHtml(string $html): Extraction
    {
        if (\strlen($html) > self::MAX_HTML_BYTES) {
            throw InvalidStructuredData::because(\sprintf('A page read for its structured data is %d bytes at most.', self::MAX_HTML_BYTES));
        }

        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8');
        $graph = Graph::of();
        $blocks = 0;
        $errors = [];

        foreach ($document->getElementsByTagName('script') as $script) {
            if ('application/ld+json' !== strtolower(trim((string) $script->getAttribute('type')))) {
                continue;
            }

            if (++$blocks > self::MAX_BLOCKS) {
                $errors[] = \sprintf('A page is read for %d JSON-LD elements at most; the others are skipped.', self::MAX_BLOCKS);

                break;
            }

            try {
                $graph = $graph->merge(self::fromJson((string) $script->textContent));
            } catch (InvalidStructuredData $invalid) {
                $errors[] = \sprintf('JSON-LD element %d: %s', $blocks, $invalid->getMessage());
            }
        }

        return new Extraction($graph, min($blocks, self::MAX_BLOCKS), $errors);
    }

    /**
     * One JSON-LD document: a node, a list of nodes, or a map with a `@graph`; the `@language` of its context becomes the
     * graph's.
     *
     * @throws InvalidStructuredData when the text is not JSON, or not a graph of nodes
     */
    public static function fromJson(string $json): Graph
    {
        try {
            $data = json_decode($json, true, self::MAX_JSON_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw InvalidStructuredData::because(\sprintf('The JSON cannot be read: %s.', $jsonException->getMessage()));
        }

        if (!\is_array($data)) {
            throw InvalidStructuredData::because('A JSON-LD document is a node, a list of nodes or a map with a @graph.');
        }

        $language = self::language($data['@context'] ?? null);
        $nodes = array_is_list($data) ? $data : ($data['@graph'] ?? [$data]);

        if (!\is_array($nodes) || !array_is_list($nodes)) {
            throw InvalidStructuredData::because('The @graph of a JSON-LD document is a list of nodes.');
        }

        $graph = Graph::of();

        foreach ($nodes as $node) {
            $graph = $graph->with(\is_array($node) && !array_is_list($node) ? Node::fromArray($node) : throw InvalidStructuredData::because('A node of a JSON-LD document is a map.'));
        }

        return $graph->withLanguage($language);
    }

    private static function language(mixed $context): ?string
    {
        foreach (\is_array($context) && array_is_list($context) ? $context : [$context] as $part) {
            if (\is_array($part) && \is_string($part['@language'] ?? null)) {
                return $part['@language'];
            }
        }

        return null;
    }
}

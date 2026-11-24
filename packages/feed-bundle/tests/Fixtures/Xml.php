<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Assert;

/**
 * A written XML feed read back by DOM: well-formed, or the test fails.
 */
final class Xml
{
    /**
     * @param array<string, string> $namespaces prefix to namespace, for the queries
     */
    public static function read(string $xml, array $namespaces = []): DOMXPath
    {
        $document = new DOMDocument();
        Assert::assertTrue($document->loadXML($xml, \LIBXML_NONET), 'The feed is well-formed XML.');
        $xpath = new DOMXPath($document);

        foreach ($namespaces as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath;
    }

    public static function count(DOMXPath $xpath, string $query): int
    {
        $nodes = $xpath->query($query);
        Assert::assertNotFalse($nodes, $query);

        return $nodes->length;
    }

    public static function text(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        Assert::assertNotFalse($nodes, $query);
        Assert::assertSame(1, $nodes->length, $query);

        return (string) $nodes->item(0)?->textContent;
    }
}

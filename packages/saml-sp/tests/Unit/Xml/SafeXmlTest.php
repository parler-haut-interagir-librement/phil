<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Xml;

use InvalidArgumentException;
use Phil\SamlSp\Exception\InvalidXml;
use Phil\SamlSp\Xml\SafeXml;
use Phil\SamlSp\Xml\XmlBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafeXml::class)]
#[CoversClass(XmlBudget::class)]
final class SafeXmlTest extends TestCase
{
    public function testAWellFormedDocumentIsRead(): void
    {
        $document = SafeXml::load('<?xml version="1.0" encoding="UTF-8"?><a xmlns="urn:x"><b c="d">é</b><!-- note --></a>', XmlBudget::message());

        self::assertSame('a', $document->documentElement?->localName);
        self::assertSame('é', $document->documentElement->textContent);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => ['', 'A document has 1 to'];
        yield 'NUL' => ["<a>\0</a>", 'A document is UTF-8 text.'];
        yield 'not UTF-8' => ["<a>\xE9</a>", 'A document is UTF-8 text.'];
        yield 'another encoding' => ['<?xml version="1.0" encoding="ISO-8859-1"?><a/>', 'A document is encoded in UTF-8.'];
        yield 'UTF-16 named' => ["<?xml version='1.0' encoding='UTF-16'?><a/>", 'A document is encoded in UTF-8.'];
        yield 'DOCTYPE' => ['<!DOCTYPE a [<!ENTITY x "y">]><a>&x;</a>', 'A document with a DOCTYPE or an entity declaration is refused.'];
        yield 'external entity' => ['<?xml version="1.0"?><!doctype a SYSTEM "file:///etc/passwd"><a/>', 'A document with a DOCTYPE or an entity declaration is refused.'];
        yield 'entity alone' => ['<a><!ENTITY x "y"></a>', 'A document with a DOCTYPE or an entity declaration is refused.'];
        yield 'processing instruction' => ['<a><?php echo 1; ?></a>', 'A document with a processing instruction or an entity is refused.'];
        yield 'not well-formed' => ['<a><b></a>', 'The document is not well-formed XML.'];
        yield 'text only' => ['hello', 'The document is not well-formed XML.'];
    }

    #[DataProvider('refused')]
    public function testWhatSamlNeverNeedsIsRefused(string $xml, string $message): void
    {
        $this->expectException(InvalidXml::class);
        $this->expectExceptionMessageIsOrContains($message);

        SafeXml::load($xml, XmlBudget::message());
    }

    public function testTheDeclarationOfUtf8IsAccepted(): void
    {
        self::assertSame('a', SafeXml::load("<?xml version='1.0' encoding='utf-8'?><a/>", XmlBudget::message())->documentElement?->localName);
    }

    public function testTheBytesAreBounded(): void
    {
        self::assertSame('a', SafeXml::load('<a>'.str_repeat('x', 3).'</a>', new XmlBudget(10))->documentElement?->localName);

        $this->expectExceptionMessageIsOrContains('A document has 1 to 10 bytes.');
        SafeXml::load('<a>'.str_repeat('x', 4).'</a>', new XmlBudget(10));
    }

    public function testTheDepthIsBounded(): void
    {
        self::assertNotNull(SafeXml::load('<a><b><c/></b></a>', new XmlBudget(maxDepth: 3))->documentElement);

        $this->expectExceptionMessageIsOrContains('A document is 3 levels deep at most.');
        SafeXml::load('<a><b><c><d/></c></b></a>', new XmlBudget(maxDepth: 3));
    }

    public function testTheNodesAreBounded(): void
    {
        self::assertNotNull(SafeXml::load('<a x="1"><b/></a>', new XmlBudget(maxNodes: 3))->documentElement);

        $this->expectExceptionMessageIsOrContains('A document has 3 nodes at most.');
        SafeXml::load('<a x="1" y="2"><b/></a>', new XmlBudget(maxNodes: 3));
    }

    public function testTextsCountAsNodes(): void
    {
        self::assertNotNull(SafeXml::load('<a>t</a>', new XmlBudget(maxNodes: 2))->documentElement);

        $this->expectExceptionMessageIsOrContains('A document has 3 nodes at most.');
        SafeXml::load('<a>t<b/>u</a>', new XmlBudget(maxNodes: 3));
    }

    public function testTheErrorsOfAnInternalCollectorAreClearedAfterTheLoad(): void
    {
        $previous = libxml_use_internal_errors(true);

        try {
            SafeXml::load('<p:a/>', XmlBudget::message());

            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function testTheLibxmlErrorsAreLeftAsTheyWere(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            SafeXml::load('<a>', XmlBudget::message());
        } catch (InvalidXml) {
        }

        self::assertFalse(libxml_use_internal_errors($previous));
        self::assertSame([], libxml_get_errors());
    }

    public function testThePresets(): void
    {
        self::assertEquals(new XmlBudget(262_144, 32, 10_000), XmlBudget::message());
        self::assertEquals(new XmlBudget(4_194_304, 48, 200_000), XmlBudget::metadata());
        self::assertSame(1, new XmlBudget(1, 1, 1)->maxNodes);
        self::assertSame(XmlBudget::MAX_NODES, new XmlBudget(XmlBudget::MAX_BYTES, XmlBudget::MAX_DEPTH, XmlBudget::MAX_NODES)->maxNodes);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function badBudgets(): iterable
    {
        yield 'no byte' => [0, 1, 1, 'The bytes of a document are between 1 and 16777216; 0 is not.'];
        yield 'too many bytes' => [XmlBudget::MAX_BYTES + 1, 1, 1, 'The bytes'];
        yield 'no depth' => [1, 0, 1, 'The depth of a document is between 1 and 256; 0 is not.'];
        yield 'too deep' => [1, XmlBudget::MAX_DEPTH + 1, 1, 'The depth'];
        yield 'no node' => [1, 1, 0, 'The nodes of a document are between 1 and 1000000; 0 is not.'];
        yield 'too many nodes' => [1, 1, XmlBudget::MAX_NODES + 1, 'The nodes'];
    }

    #[DataProvider('badBudgets')]
    public function testABudgetIsBounded(int $bytes, int $depth, int $nodes, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new XmlBudget($bytes, $depth, $nodes);
    }
}

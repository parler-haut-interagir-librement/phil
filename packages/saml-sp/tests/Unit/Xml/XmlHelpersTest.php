<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Xml;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMException;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\IdMap;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSp\Xml\XmlWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Elements::class)]
#[CoversClass(IdMap::class)]
#[CoversClass(Instant::class)]
#[CoversClass(Base64::class)]
#[CoversClass(XmlWriter::class)]
final class XmlHelpersTest extends TestCase
{
    public function testElementsReadsTheDirectChildrenOnly(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a xmlns="urn:a" xmlns:b="urn:b" k="v" e=""><x>1</x><b:x>2</b:x><y><x>deep</x></y><x> 3 <!-- c --> </x></a>');

        $root = $document->documentElement;
        self::assertNotNull($root);

        self::assertSame(['1', '3'], array_map(Elements::text(...), Elements::all($root, 'urn:a', 'x')));
        self::assertNull(Elements::one($root, 'urn:a', 'x'));
        self::assertSame('2', Elements::one($root, 'urn:b', 'x')?->textContent);
        self::assertNull(Elements::one($root, 'urn:b', 'z'));
        self::assertSame('v', Elements::attribute($root, 'k'));
        self::assertSame('', Elements::attribute($root, 'e'));
        self::assertNull(Elements::attribute($root, 'missing'));
        self::assertTrue(Elements::is($root, 'urn:a', 'a'));
        self::assertFalse(Elements::is($root, 'urn:b', 'a'));
        self::assertFalse(Elements::is($root, 'urn:a', 'x'));
        self::assertSame(3, Elements::countInDocument($root, 'urn:a', 'x'));
    }

    public function testAFreeElementCountsNothing(): void
    {
        $element = new DOMElement('free');

        self::assertSame(0, Elements::countInDocument($element, 'urn:a', 'x'));
    }

    public function testIdMapFindsTheElementsByTheirId(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a ID="1" xmlns:p="urn:p"><b ID="2"/><c p:ID="1"/><d id="2"/></a>');

        $map = IdMap::of($document);

        self::assertSame('a', $map->element('1')?->localName);
        self::assertSame('b', $map->element('2')?->localName);
        self::assertNull($map->element('3'));
    }

    public function testIdMapRefusesARepeatedId(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a ID="1"><b><c ID="1"/></b></a>');

        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('Two elements of the document carry the same ID.');

        IdMap::of($document);
    }

    public function testInstantsAreReadInUtc(): void
    {
        self::assertSame('2026-11-19 12:00:00.000000', Instant::parse('2026-11-19T12:00:00Z', 'x')->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-19 12:00:00.123000', Instant::parse('2026-11-19T12:00:00.123Z', 'x')->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-19 12:00:00.123456', Instant::parse('2026-11-19T12:00:00.1234567Z', 'x')->format('Y-m-d H:i:s.u'));
        self::assertSame('+00:00', Instant::parse('2026-11-19T12:00:00Z', 'x')->format('P'));
        self::assertSame('2026-11-19T17:00:00Z', Instant::format(new DateTimeImmutable('2026-11-19 12:00:00.9', new DateTimeZone('America/Toronto'))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badInstants(): iterable
    {
        yield 'offset' => ['2026-11-19T12:00:00+01:00'];
        yield 'no zone' => ['2026-11-19T12:00:00'];
        yield 'date' => ['2026-11-19'];
        yield 'impossible' => ['2026-02-30T12:00:00Z'];
        yield 'too many digits' => ['2026-11-19T12:00:00.1234567890Z'];
        yield 'trailing' => ["2026-11-19T12:00:00Z\n"];
        yield 'leading' => [' 2026-11-19T12:00:00Z'];
        yield 'empty' => [''];
    }

    #[DataProvider('badInstants')]
    public function testAnInstantNotInUtcIsRefused(string $text): void
    {
        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains('The thing is not an instant in UTC.');

        Instant::parse($text, 'The thing');
    }

    public function testBase64(): void
    {
        self::assertSame('aGVsbG8=', Base64::encode('hello'));
        self::assertSame('hello', Base64::decode("aGVs\r\n bG8=\t"));
        self::assertNull(Base64::decode(''));
        self::assertNull(Base64::decode('aGVsbG8'));
        self::assertNull(Base64::decode('a-_b'));
        self::assertNull(Base64::decode('***'));
    }

    public function testXmlWriterEscapesAndLeavesOutNullAttributes(): void
    {
        $root = XmlWriter::document('urn:a', 'a:root');
        $child = XmlWriter::child($root, 'urn:a', 'a:child', ['k' => 'x & "y"', 'gone' => null], '<b> & c');

        self::assertSame('<a:root xmlns:a="urn:a"><a:child k="x &amp; &quot;y&quot;">&lt;b&gt; &amp; c</a:child></a:root>', XmlWriter::xml($root));
        self::assertFalse($child->hasAttribute('gone'));
        self::assertSame('<a:child/>', XmlWriter::xml(XmlWriter::child(XmlWriter::document('urn:a', 'a:r'), 'urn:a', 'a:child')));
        self::assertSame('urn:a', $root->ownerDocument?->documentElement?->namespaceURI);
        self::assertSame('UTF-8', $root->ownerDocument->encoding);
    }

    public function testXmlWriterRefusesAnInvalidName(): void
    {
        $this->expectException(DOMException::class);

        XmlWriter::element(new DOMDocument(), 'urn:a', '1:bad');
    }
}

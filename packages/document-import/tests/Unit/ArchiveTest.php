<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Dom\XMLDocument;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Zip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The XML of an entry parsed without network access nor document type, and the paths of relationships.
 */
#[CoversClass(Archive::class)]
#[CoversClass(Zip::class)]
final class ArchiveTest extends TestCase
{
    public function testAnAbsentEntryIsNull(): void
    {
        self::assertNull(Archive::of([])->xml('a.xml'));
    }

    public function testAnEmptyEntryIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::because('The entry "a.xml" is not well-formed XML.'));

        Archive::of(['a.xml' => ''])->xml('a.xml');
    }

    public function testAnEntryOfSpacesIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::because('The entry "a.xml" is not well-formed XML.'));

        Archive::of(['a.xml' => " \n"])->xml('a.xml');
    }

    public function testMalformedXmlIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::because('The entry "a.xml" is not well-formed XML.'));

        Archive::of(['a.xml' => '<a>'])->xml('a.xml');
    }

    public function testNamespacesAreReplaced(): void
    {
        $document = Archive::of(['a.xml' => '<a xmlns="old"/>'])->xml('a.xml', ['old' => 'new']);

        self::assertInstanceOf(XMLDocument::class, $document);
        self::assertSame('new', $document->documentElement?->namespaceURI);
    }

    public function testXmlThatTheParserWouldWarnAboutIsRead(): void
    {
        $prefix = Archive::of(['a.xml' => '<x:a/>'])->xml('a.xml');
        $relative = Archive::of(['a.xml' => '<a xmlns="relative"/>'])->xml('a.xml');

        self::assertInstanceOf(XMLDocument::class, $prefix);
        self::assertInstanceOf(XMLDocument::class, $relative);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function doctypes(): iterable
    {
        // A declaration that is not even well-formed is told by the document type before the parser reads it.
        $malformed = '<!DOCTYPE a [ <!ENTITY e "&f;" ]>';
        $declares = 'The entry "a.xml" declares a document type, which no office document does.';
        $unreadable = 'The entry "a.xml" is not well-formed XML.';

        yield 'at the start' => [$malformed.'<a/>', $declares];
        yield 'after spaces' => [" \n".$malformed.'<a/>', $declares];
        yield 'after a declaration' => ['<?xml version="1.0"?>'."\n".$malformed.'<a/>', $declares];
        yield 'after a comment on two lines' => ["<!-- one\ntwo -->\n".$malformed.'<a/>', $declares];
        yield 'well-formed' => ['<!DOCTYPE a [ <!ENTITY e "x"> ]><a/>', $declares];
        yield 'ending on the 4 096th byte' => [str_repeat(' ', 4096 - 9).'<!DOCTYPE a [ <!ENTITY e "&f;" ]><a/>', $declares];
        yield 'ending on the 4 097th byte' => [str_repeat(' ', 4097 - 9).'<!DOCTYPE a [ <!ENTITY e "&f;" ]><a/>', $unreadable];
        yield 'long and well-formed' => [str_repeat(' ', 5000).'<!DOCTYPE a [ <!ENTITY e "x"> ]><a/>', $declares];
    }

    #[DataProvider('doctypes')]
    public function testADocumentTypeIsRefused(string $xml, string $message): void
    {
        try {
            Archive::of(['a.xml' => $xml])->xml('a.xml');
            self::fail('A document type is read.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame($message, $invalidDocument->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function paths(): iterable
    {
        yield 'a sibling' => ['word', 'media/a.png', 'word/media/a.png'];
        yield 'from the root' => ['word', '/media/a.png', 'media/a.png'];
        yield 'a folder up' => ['word/deep', '../media/a.png', 'word/media/a.png'];
        yield 'the same folder' => ['word', './a.png', 'word/a.png'];
        yield 'a double slash' => ['word', 'media//a.png', 'word/media/a.png'];
        yield 'above the root' => ['.', '../a.png', 'a.png'];
        yield 'the current folder as folder' => ['.', 'a.png', 'a.png'];
    }

    #[DataProvider('paths')]
    public function testAPathIsResolvedFromItsFolder(string $folder, string $target, string $expected): void
    {
        self::assertSame($expected, Archive::resolve($folder, $target));
    }
}

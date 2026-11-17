<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Exception\InvalidLimits;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Warnings;
use Phil\ElearningPackages\Xml\XmlDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackagePath::class)]
#[CoversClass(XmlDocument::class)]
#[CoversClass(PackageLimits::class)]
#[CoversClass(Warnings::class)]
#[CoversClass(PackageRefused::class)]
final class SafetyTest extends TestCase
{
    public function testAPathInsideThePackageIsKept(): void
    {
        self::assertSame('a/b c/é.html', PackagePath::check('a/b c/é.html'));
        self::assertSame('index.html', PackagePath::check('index.html'));
        self::assertSame(str_repeat('a', 1024), PackagePath::check(str_repeat('a', 1024)));
        self::assertSame(implode('/', array_fill(0, 32, 'a')), PackagePath::check(implode('/', array_fill(0, 32, 'a'))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafePaths(): iterable
    {
        yield 'empty' => ['', 'A path in a package is 1 to 1024 bytes of UTF-8.'];
        yield 'too long' => [str_repeat('a', 1025), 'A path in a package is 1 to 1024 bytes of UTF-8.'];
        yield 'not UTF-8' => ["a\xFF.html", 'A path in a package is 1 to 1024 bytes of UTF-8.'];
        yield 'parent' => ['../etc/passwd', 'holds an empty, "." or ".." segment'];
        yield 'inner parent' => ['a/../../b', 'holds an empty, "." or ".." segment'];
        yield 'dot' => ['./a', 'holds an empty, "." or ".." segment'];
        yield 'trailing dot' => ['a/.', 'holds an empty, "." or ".." segment'];
        yield 'double slash' => ['a//b', 'holds an empty, "." or ".." segment'];
        yield 'trailing slash' => ['a/', 'holds an empty, "." or ".." segment'];
        yield 'absolute' => ['/etc/passwd', 'is absolute, or holds a backslash'];
        yield 'backslash' => ['a\\..\\b', 'is absolute, or holds a backslash'];
        yield 'drive' => ['C:/Windows', 'is absolute, or holds a backslash'];
        yield 'nul' => ["a\0.html", 'is absolute, or holds a backslash'];
        yield 'new line' => ["a\n.html", 'is absolute, or holds a backslash'];
        yield 'delete' => ["a\x7F.html", 'is absolute, or holds a backslash'];
        yield 'deep' => [implode('/', array_fill(0, 33, 'a')), 'is deeper than 32 directories'];
    }

    #[DataProvider('unsafePaths')]
    public function testAPathLeavingThePackageIsRefused(string $path, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        PackagePath::check($path);
    }

    public function testTheRefusalShowsThePathSafelyAndShort(): void
    {
        try {
            PackagePath::check("/\n".str_repeat('a', 200));
            self::fail('The path is accepted.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The path "/?'.str_repeat('a', 118).'" is absolute, or holds a backslash, a colon or a control character.', $packageRefused->getMessage());
        }
    }

    public function testAReferenceIsResolvedAgainstItsBases(): void
    {
        self::assertSame(['content/tools/saws.html', '?lang=en'], PackagePath::resolve(['content/', 'tools/'], 'saws.html?lang=en'));
        self::assertSame(['content/a b.html', '#top'], PackagePath::resolve(['/content/'], './a%20b.html#top'));
        self::assertSame(['a.html', null], PackagePath::resolve([], 'a.html'));
        self::assertSame(['a/b.html', '?x=1#y'], PackagePath::resolve(['a'], 'b.html?x=1#y'));

        foreach (['../a.html', '%2e%2e/a.html', 'a/%2E%2E/%2e%2e/b', 'a%00.html', ''] as $reference) {
            try {
                PackagePath::resolve(['content'], $reference);
                self::assertSame('', $reference, 'The empty reference names the base itself.');
            } catch (PackageRefused) {
                self::assertNotSame('', $reference);
            }
        }

        $this->expectException(PackageRefused::class);
        PackagePath::resolve([], '');
    }

    public function testAWebAddressIsToldApart(): void
    {
        self::assertTrue(PackagePath::isWebAddress('https://example.org/'));
        self::assertTrue(PackagePath::isWebAddress('HTTP://example.org/'));
        self::assertFalse(PackagePath::isWebAddress('content/https://x'));
        self::assertFalse(PackagePath::isWebAddress('ftp://example.org/'));
    }

    public function testAnXmlDocumentIsReadByLocalNames(): void
    {
        $root = XmlDocument::root('<?xml version="1.0"?><a:root xmlns:a="urn:a" xmlns:b="urn:b" b:key=" v "><a:item>  one </a:item><item>two</item><other/></a:root>', 1000, 'The test');

        self::assertSame('root', $root->localName);
        self::assertCount(2, XmlDocument::children($root, 'item'));
        self::assertSame('one', XmlDocument::childText($root, 'item'));
        self::assertNull(XmlDocument::childText($root, 'missing'));
        self::assertNull(XmlDocument::child($root, 'missing'));
        self::assertSame('v', XmlDocument::attribute($root, 'other', 'key'));
        self::assertNull(XmlDocument::attribute($root, 'missing'));
        self::assertSame('<other/>', XmlDocument::markup(XmlDocument::children($root, 'other')[0], 8));
        self::assertNull(XmlDocument::markup(XmlDocument::children($root, 'other')[0], 7));
        self::assertSame(4096, mb_strlen(XmlDocument::text(XmlDocument::root('<a>'.str_repeat('é', 5000).'</a>', 20_000, 'The test'))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeDocuments(): iterable
    {
        yield 'too large' => ['<a>'.str_repeat('a', 100).'</a>', 'The test is larger than 100 bytes.'];
        yield 'document type' => ['<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x "y">]><a>&x;</a>', 'declares a document type or an entity'];
        yield 'external entity' => ['<?xml version="1.0"?><!doctype a SYSTEM "file:///etc/passwd"><a/>', 'declares a document type or an entity'];
        yield 'entity alone' => ['<a><!ENTITY x "y"></a>', 'declares a document type or an entity'];
        yield 'malformed' => ['<a><b></a>', 'The test is not well-formed XML.'];
        yield 'empty' => ['  ', 'The test is not well-formed XML.'];
        yield 'text' => ['plain text', 'The test is not well-formed XML.'];
    }

    #[DataProvider('unsafeDocuments')]
    public function testAnUnsafeDocumentIsRefused(string $xml, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        XmlDocument::root($xml, 100, 'The test');
    }

    public function testTheErrorsOfLibxmlAreLeftAsTheyWere(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            XmlDocument::root('<a>', 100, 'The test');
        } catch (PackageRefused) {
            self::assertFalse(libxml_use_internal_errors(false));
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testTheLimitsArePositive(): void
    {
        $limits = new PackageLimits();
        self::assertSame([512 * 1024 * 1024, 10_000, 256 * 1024 * 1024, 2 * 1024 * 1024 * 1024, 200, 4 * 1024 * 1024, 16, 5000], [$limits->archiveBytes, $limits->entries, $limits->entryBytes, $limits->totalBytes, $limits->ratio, $limits->manifestBytes, $limits->depth, $limits->items]);
        self::assertSame(1, new PackageLimits(1, 1, 1, 1, 1, 1, 1, 1)->items);

        for ($position = 0; $position < 8; ++$position) {
            $values = array_fill(0, 8, 1);
            $values[$position] = 0;

            try {
                new PackageLimits(...$values);
                self::fail('The limits are accepted.');
            } catch (InvalidLimits $invalidLimits) {
                self::assertSame('Every limit of a package is a positive number.', $invalidLimits->getMessage());
            }
        }
    }

    public function testTheWarningsAreSaidOnceAndBounded(): void
    {
        $warnings = new Warnings();
        $warnings->add('One');
        $warnings->add('One');
        $warnings->add('Two');
        self::assertSame(['One', 'Two'], $warnings->all());

        $many = new Warnings();

        for ($index = 0; $index < 103; ++$index) {
            $many->add('Note '.$index);
        }

        $many->add('Note 0');
        $all = $many->all();
        self::assertCount(101, $all);
        self::assertSame('Note 99', $all[99]);
        self::assertSame('3 more notes are left out.', $all[100]);
    }
}

<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

use PHPUnit\Framework\Assert;

/**
 * Reads back what the tests write: each object by number through the cross-reference table, which it checks, and
 * each stream decompressed.
 */
final readonly class PdfReader
{
    /** @var array<int, string> each object's text, by number */
    private array $objects;

    public function __construct(
        public string $pdf,
    ) {
        Assert::assertStringStartsWith("%PDF-1.7\n%\xE2\xE3\xCF\xD3\n", $pdf);
        Assert::assertStringEndsWith("%%EOF\n", $pdf);
        Assert::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\n\z/', $pdf, $start));
        $xref = (int) $start[1];
        Assert::assertSame("xref\n", substr($pdf, $xref, 5));
        Assert::assertSame(1, preg_match('/\Axref\n0 (\d+)\n0000000000 65535 f \n/', substr($pdf, $xref), $header));
        $objects = [];

        for ($number = 1, $count = (int) $header[1]; $number < $count; ++$number) {
            $entry = substr($pdf, $xref + \strlen($header[0]) + 20 * ($number - 1), 20);
            Assert::assertMatchesRegularExpression('/\A(\d{10}) 00000 n \n\z/', $entry, 'Entry '.$number.' is a 20-byte line.');
            $offset = (int) $entry;
            $prefix = $number.' 0 obj'."\n";
            Assert::assertSame($prefix, substr($pdf, $offset, \strlen($prefix)), 'The offset of object '.$number.' points at it.');
            $end = strpos($pdf, "\nendobj\n", $offset);
            Assert::assertIsInt($end);
            $objects[$number] = substr($pdf, $offset + \strlen($prefix), $end - $offset - \strlen($prefix));
        }

        Assert::assertMatchesRegularExpression('/trailer\n<< \/Size '.\count($objects) + 1 .' \/Root (\d+) 0 R \/Info (\d+) 0 R(?: \/ID \[<([0-9A-F]{32})> <\3>\])? >>\n/', $pdf);
        $this->objects = $objects;
    }

    public function object(int $number): string
    {
        Assert::assertArrayHasKey($number, $this->objects);

        return $this->objects[$number];
    }

    /**
     * The first object whose dictionary holds the text given.
     */
    public function find(string $text): string
    {
        foreach ($this->objects as $object) {
            $dictionary = strstr($object, "\nstream\n", true);

            if (str_contains(false === $dictionary ? $object : $dictionary, $text)) {
                return $object;
            }
        }

        Assert::fail('No object holds '.$text);
    }

    /**
     * A stream's data, decompressed when it is compressed, checked against its declared length.
     */
    public static function data(string $object): string
    {
        Assert::assertSame(1, preg_match('/\/Length (\d+) >>\nstream\n/', $object, $length, \PREG_OFFSET_CAPTURE));
        $start = $length[0][1] + \strlen($length[0][0]);
        $data = substr($object, $start, (int) $length[1][0]);
        Assert::assertSame("\nendstream", substr($object, $start + (int) $length[1][0]), 'The length of a stream is exact.');

        return str_contains(substr($object, 0, $start), '/Filter /FlateDecode') && !str_contains($object, '/Subtype /Image') ? (string) gzuncompress($data) : $data;
    }

    /**
     * Every page's content stream, in order, decompressed.
     */
    public function contents(): string
    {
        $contents = '';

        foreach ($this->objects as $object) {
            if (str_starts_with($object, '<< /Type /Page ') && 1 === preg_match('/\/Contents (\d+) 0 R/', $object, $match)) {
                $contents .= self::data($this->object((int) $match[1]));
            }
        }

        return $contents;
    }
}

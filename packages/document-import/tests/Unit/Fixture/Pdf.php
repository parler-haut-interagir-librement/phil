<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

/**
 * A PDF written for a test: its pages, each a content stream, with the fonts, images and annotations a test adds,
 * written with a cross-reference table, a cross-reference stream with its objects in an object stream, or no cross
 * reference at all.
 */
final class Pdf
{
    /** @var array<int, string> */
    private array $objects = [];

    /** @var list<int> */
    private array $pages = [];

    private string $info = '';

    private string $catalog = '';

    public function __construct()
    {
        $this->objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $this->objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $this->objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
    }

    /**
     * A page and its content; `$extra` adds entries to its dictionary, such as `/Annots`.
     */
    public function page(string $content, string $extra = '', bool $compressed = false, string $resources = ''): self
    {
        $stream = $compressed ? (string) gzcompress($content) : $content;
        $contents = $this->add('<< /Length '.\strlen($stream).($compressed ? ' /Filter /FlateDecode' : '').' >>'."\nstream\n".$stream."\nendstream");
        $this->pages[] = $this->add('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.$contents.' 0 R /Resources << /Font << /F1 3 0 R /B 4 0 R /I 5 0 R >>'.$resources.' >>'.$extra.' >>');

        return $this;
    }

    /**
     * An object of the file, by the number it gets.
     */
    public function add(string $object): int
    {
        $number = max(5, ...array_keys($this->objects)) + 1;
        $this->objects[$number] = $object;

        return $number;
    }

    public function info(string $info): self
    {
        $this->info = $info;

        return $this;
    }

    public function catalog(string $entries): self
    {
        $this->catalog = $entries;

        return $this;
    }

    /**
     * @param 'table'|'stream'|'none' $references
     */
    public function bytes(string $references = 'table'): string
    {
        /** @var non-empty-array<int, string> $objects */
        $objects = $this->objects;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R'.$this->catalog.' >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(static fn (int $page): string => $page.' 0 R', $this->pages)).'] /Count '.\count($this->pages).' >>';
        $info = '' === $this->info ? null : max(array_keys($objects)) + 1;

        if (null !== $info) {
            $objects[$info] = '<< '.$this->info.' >>';
        }

        ksort($objects);

        return 'stream' === $references ? $this->compressed($objects, $info) : $this->plain($objects, $info, 'table' === $references);
    }

    /**
     * @param non-empty-array<int, string> $objects
     */
    private function plain(array $objects, ?int $info, bool $table): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $trailer = '<< /Size '.(max(array_keys($objects)) + 1).' /Root 1 0 R'.(null === $info ? '' : ' /Info '.$info.' 0 R').' >>';

        if (!$table) {
            return $pdf.'trailer '.$trailer."\n%%EOF\n";
        }

        $xref = \strlen($pdf);
        $pdf .= "xref\n0 ".(max(array_keys($objects)) + 1)."\n0000000000 65535 f \n";

        for ($number = 1; $number <= max(array_keys($objects)); ++$number) {
            $pdf .= isset($offsets[$number]) ? \sprintf("%010d 00000 n \n", $offsets[$number]) : "0000000000 65535 f \n";
        }

        return $pdf.'trailer '.$trailer."\nstartxref\n".$xref."\n%%EOF\n";
    }

    /**
     * The dictionaries in an object stream, the streams outside it, and a cross-reference stream.
     *
     * @param non-empty-array<int, string> $objects
     */
    private function compressed(array $objects, ?int $info): string
    {
        $pdf = "%PDF-1.5\n";
        $offsets = [];
        $inner = [];

        foreach ($objects as $number => $object) {
            if (str_contains($object, 'stream')) {
                $offsets[$number] = \strlen($pdf);
                $pdf .= $number." 0 obj\n".$object."\nendobj\n";

                continue;
            }

            $inner[$number] = $object;
        }

        $streamNumber = max(array_keys($objects)) + 1;
        $header = '';
        $body = '';
        $index = 0;
        $positions = [];

        foreach ($inner as $number => $object) {
            $header .= $number.' '.\strlen($body).' ';
            $body .= $object."\n";
            $positions[$number] = $index++;
        }

        $data = (string) gzcompress($header.$body);
        $offsets[$streamNumber] = \strlen($pdf);
        $pdf .= $streamNumber." 0 obj\n<< /Type /ObjStm /N ".\count($inner).' /First '.\strlen($header).' /Filter /FlateDecode /Length '.\strlen($data)." >>\nstream\n".$data."\nendstream\nendobj\n";
        $xrefNumber = $streamNumber + 1;
        $offsets[$xrefNumber] = \strlen($pdf);
        $rows = "\x00\x00\x00\x00";

        for ($number = 1; $number <= $xrefNumber; ++$number) {
            $rows .= match (true) {
                isset($positions[$number]) => "\x02".pack('n', $streamNumber).\chr($positions[$number] & 0xFF),
                isset($offsets[$number]) => "\x01".pack('n', $offsets[$number]).\chr(0),
                default => "\x00\x00\x00\x00",
            };
        }

        $predicted = '';

        foreach (str_split($rows, 4) as $row) {
            $predicted .= "\x00".$row;
        }

        $data = (string) gzcompress($predicted);
        $pdf .= $xrefNumber." 0 obj\n<< /Type /XRef /Size ".($xrefNumber + 1).' /W [1 2 1] /Root 1 0 R'.(null === $info ? '' : ' /Info '.$info.' 0 R')
            .' /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 4 >> /Length '.\strlen($data)." >>\nstream\n".$data."\nendstream\nendobj\n";

        return $pdf.'startxref'."\n".$offsets[$xrefNumber]."\n%%EOF\n";
    }
}

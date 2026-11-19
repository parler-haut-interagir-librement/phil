<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

/**
 * A PDF written byte by byte for a test of its structure: objects appended where the test says, their offsets kept,
 * cross-reference tables and streams written as the test lays them out — damaged, chained, or pointing past a decoy.
 */
final class RawPdf
{
    public string $bytes = "%PDF-1.7\n";

    /** @var array<int, int> the offset of the last definition of each object */
    public array $offsets = [];

    /**
     * Appends an object and answers its offset.
     */
    public function object(int $number, string $body): int
    {
        $offset = \strlen($this->bytes);
        $this->offsets[$number] = $offset;
        $this->bytes .= $number." 0 obj\n".$body."\nendobj\n";

        return $offset;
    }

    /**
     * Appends a stream object, its length given.
     */
    public function stream(int $number, string $dictionary, string $data): int
    {
        return $this->object($number, '<< '.$dictionary.' /Length '.\strlen($data)." >>\nstream\n".$data."\nendstream");
    }

    public function append(string $bytes): int
    {
        $offset = \strlen($this->bytes);
        $this->bytes .= $bytes;

        return $offset;
    }

    /**
     * Appends a cross-reference table of subsections — each a first number and the offsets of its objects, `null`
     * for a free one — and its trailer, and answers its offset.
     *
     * @param array<int, list<int|null>> $subsections
     */
    public function table(array $subsections, string $trailer): int
    {
        $table = "xref\n";

        foreach ($subsections as $first => $offsets) {
            $table .= $first.' '.\count($offsets)."\n";

            foreach ($offsets as $offset) {
                $table .= null === $offset ? "0000000000 65535 f \n" : \sprintf("%010d 00000 n \n", $offset);
            }
        }

        return $this->append($table.'trailer '.$trailer."\n");
    }

    /**
     * Appends a cross-reference stream of rows already packed.
     */
    public function xrefStream(int $number, string $dictionary, string $rows): int
    {
        return $this->stream($number, '/Type /XRef '.$dictionary, $rows);
    }

    public function startxref(int $offset): string
    {
        return $this->bytes.'startxref'."\n".$offset."\n%%EOF\n";
    }
}

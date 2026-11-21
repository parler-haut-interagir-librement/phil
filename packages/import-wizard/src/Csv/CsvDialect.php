<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Csv;

use Phil\ImportWizard\Exception\InvalidProfile;

/**
 * How a CSV file is written: its delimiter, its quote, its encoding, whether a written file starts with a byte order
 * mark — the mark that tells a spreadsheet the file is UTF-8 —, and whether cells that a spreadsheet would run as a
 * formula are written with a leading apostrophe, and read back without it.
 */
final readonly class CsvDialect
{
    public const array ENCODINGS = ['UTF-8', 'Windows-1252', 'ISO-8859-1'];

    private const array DELIMITERS = [',', ';', "\t", '|'];

    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $encoding = 'UTF-8',
        public bool $byteOrderMark = true,
        public bool $guardFormulas = true,
    ) {
        if (!\in_array($delimiter, self::DELIMITERS, true) || '"' !== $enclosure && "'" !== $enclosure) {
            throw InvalidProfile::because('The delimiter must be a comma, a semicolon, a tab or a bar, the quote " or \'.');
        }

        if (!\in_array($encoding, self::ENCODINGS, true)) {
            throw InvalidProfile::because('The encoding must be UTF-8, Windows-1252 or ISO-8859-1.');
        }
    }

    /**
     * The dialect whose delimiter is the one the first line of the content holds the most of, a comma when none.
     */
    public static function detect(string $content, string $encoding = 'UTF-8'): self
    {
        $end = strcspn($content, "\r\n");
        $line = substr($content, 0, $end);
        $counts = [];

        foreach (self::DELIMITERS as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }

        $best = array_search(max($counts), $counts, true);

        return new self(\is_string($best) ? $best : ',', encoding: $encoding);
    }
}

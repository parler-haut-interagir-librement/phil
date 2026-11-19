<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Output;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\Exception\UnrepresentableCell;

/**
 * A book as a BRF file: North American ASCII braille, each cell one printable ASCII character, each row ended by a
 * carriage return and a line feed, each page by a form feed. Embossers and braille notetakers read it as it is.
 *
 * BRF has six dots: a cell with dot 7 or 8 stops the writing.
 */
final readonly class BrfWriter
{
    /** The ASCII character of each six-dot cell, by the bits of its dots, dot 1 the lowest. */
    private const string ASCII = ' A1B\'K2L@CIF/MSP"E3H9O6R^DJG>NTQ,*5<-U8V.%[$+X!&;:4\\0Z7(_?W]#Y)=';

    /** @var array<string, string> */
    private array $map;

    public function __construct()
    {
        $map = [];

        foreach (str_split(self::ASCII) as $mask => $character) {
            $map[mb_chr(0x2800 + $mask, 'UTF-8')] = $character;
        }

        $this->map = $map;
    }

    /**
     * @throws UnrepresentableCell
     */
    public function write(Book $book): string
    {
        $brf = '';

        foreach ($book->pages() as $page) {
            foreach ($page->rows as $index => $row) {
                $ascii = strtr($row, $this->map);

                if (1 === preg_match('/[^\x20-\x7E]/', $ascii)) {
                    throw UnrepresentableCell::eightDots($page->number, $index + 1);
                }

                $brf .= $ascii."\r\n";
            }

            $brf .= "\f";
        }

        return $brf;
    }
}

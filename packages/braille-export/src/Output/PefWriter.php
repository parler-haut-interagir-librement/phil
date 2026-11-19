<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Output;

use Phil\BrailleExport\Book\Book;

/**
 * A book as a PEF file, the Portable Embosser Format 2008-1 of the DAISY Consortium: XML that keeps the volumes, the
 * sections, the pages and the rows of the book, each row in Unicode braille, with its Dublin Core metadata.
 */
final readonly class PefWriter
{
    public const string XML_NAMESPACE = 'http://www.daisy.org/ns/2008/pef';

    public const string MEDIA_TYPE = 'application/x-pef+xml';

    public function write(Book $book, PefMetadata $metadata = new PefMetadata()): string
    {
        $format = $book->format;
        $body = '';

        foreach ($book->volumes as $volume) {
            $body .= \sprintf('<volume cols="%d" rows="%d" rowgap="0" duplex="%s">', $format->cellsPerLine, $format->linesPerPage, $format->duplex ? 'true' : 'false');

            foreach ($volume->sections as $section) {
                $body .= '<section>';

                foreach ($section->pages as $page) {
                    $body .= '<page>'.implode('', array_map(static fn (string $row): string => '' === $row ? '<row/>' : '<row>'.$row.'</row>', [] === $page->rows ? [''] : $page->rows)).'</page>';
                }

                $body .= '</section>';
            }

            $body .= '</volume>';
        }

        $meta = $this->element('dc:format', self::MEDIA_TYPE)
            .$this->element('dc:identifier', $metadata->identifier ?? 'urn:sha256:'.hash('sha256', $body))
            .$this->element('dc:title', $book->title)
            .$this->element('dc:creator', $metadata->creator)
            .$this->element('dc:publisher', $metadata->publisher)
            .$this->element('dc:language', $book->language)
            .$this->element('dc:date', $metadata->date?->format('Y-m-d') ?? '');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<pef version="2008-1" xmlns="'.self::XML_NAMESPACE.'">'
            .'<head><meta xmlns:dc="http://purl.org/dc/elements/1.1/">'.$meta.'</meta></head>'
            .'<body>'.$body.'</body></pef>'."\n";
    }

    /**
     * An element of the metadata, its text cleared of what XML 1.0 cannot hold; nothing for an empty text.
     */
    private function element(string $name, string $text): string
    {
        $text = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8'));

        return '' === $text ? '' : '<'.$name.'>'.htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8').'</'.$name.'>';
    }
}

<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Benchmark;

use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Tests\Unit\Fixture\Pdf;
use Phil\Ujo\Document;

/**
 * What an import of the new readers pays for a book of fifty pages: a PDF of compressed pages of headings, paragraphs
 * and lists, an RTF of the same length, and the export of the document they give as DOCX.
 */
final readonly class ReadersBench
{
    private string $pdf;

    private string $rtf;

    private Document $document;

    public function __construct()
    {
        $book = new Pdf();
        $rtf = '{\rtf1\ansi{\stylesheet{\s1 heading 1;}}';

        for ($page = 1; $page <= 50; ++$page) {
            $content = 'BT /F1 18 Tf 72 740 Td (Chapter '.$page.') Tj ET';

            for ($line = 0; $line < 40; ++$line) {
                $content .= ' BT /F1 11 Tf 72 '.(700 - 15 * $line).' Td (Line '.$line.' of a paragraph that goes on with ordinary words.) Tj ET';
            }

            $book->page($content, compressed: true);
            $rtf .= '\pard\s1 Chapter '.$page.'\par'.str_repeat('\pard Line of a paragraph that goes on with {\b ordinary} words.\par', 40);
        }

        $this->pdf = $book->bytes('stream');
        $this->rtf = $rtf.'}';
        $this->document = new DocumentImporter()->import($this->pdf, Format::Pdf)->document;
    }

    public function benchImportAPdfOfFiftyPages(): void
    {
        new DocumentImporter()->import($this->pdf, Format::Pdf);
    }

    public function benchImportAnRtfOfFiftyPages(): void
    {
        new DocumentImporter()->import($this->rtf, Format::Rtf);
    }

    public function benchExportFiftyPagesAsDocx(): void
    {
        new DocumentExporter()->export($this->document, Format::Docx);
    }
}

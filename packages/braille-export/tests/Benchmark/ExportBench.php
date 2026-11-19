<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Benchmark;

use Phil\BrailleExport\Book\Book;
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\PrintPage;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Output\PefWriter;
use Phil\BrailleExport\Translation\UncontractedTranslator;

/**
 * A chapter of 200 paragraphs of French, about 90 braille pages of 40 by 25: translated, laid out, then written as BRF
 * and as PEF.
 *
 * @Groups({"braille-export"})
 */
final readonly class ExportBench
{
    private BrailleExporter $exporter;

    private Document $document;

    private Book $book;

    public function __construct()
    {
        $this->exporter = new BrailleExporter(new UncontractedTranslator());
        $blocks = [new Heading('Chapitre premier')];

        for ($paragraph = 1; $paragraph <= 200; ++$paragraph) {
            if (0 === $paragraph % 10) {
                $blocks[] = new PrintPage((string) intdiv($paragraph, 10));
                $blocks[] = new Heading('Section '.$paragraph, 2);
            }

            $blocks[] = new Paragraph("Lorsque j'avais six ans, j'ai vu, une fois, une magnifique image, dans un livre sur la forêt vierge qui s'appelait « Histoires vécues ». Ça représentait un serpent boa qui avalait un fauve, en 1943.");
        }

        $this->document = new Document($blocks, 'fr');
        $this->book = $this->exporter->layout($this->document);
    }

    public function benchLayout(): void
    {
        $this->exporter->layout($this->document, Contraction::Uncontracted);
    }

    public function benchBrf(): void
    {
        new BrfWriter()->write($this->book);
    }

    public function benchPef(): void
    {
        new PefWriter()->write($this->book);
    }
}

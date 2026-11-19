<?php

declare(strict_types=1);

use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\ListItem;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\PrintPage;
use Phil\BrailleExport\Document\Table;
use Phil\BrailleExport\Layout\PageFormat;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Translation\Braille;
use Phil\BrailleExport\Translation\UncontractedTranslator;

/*
 * A text nobody wrote, translated in French and in English and laid out on a narrow page as each kind of block: every
 * row is braille, no row is wider than the line, no page longer than the page, and the BRF file can always be written.
 */
$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$format = new PageFormat(12, 4, pagesPerVolume: 3, maxPages: 2_000);
$exporter = new BrailleExporter(new UncontractedTranslator(), $format);
$brf = new BrfWriter();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($exporter, $format, $brf): void {
    $parts = explode('|', $input);
    $blocks = [
        new PrintPage($parts[0]),
        new Heading($input, 1),
        new Paragraph($input),
        new Heading($input, 2),
        new ListBlock([new ListItem($input, new ListBlock([new ListItem($input)]))], true),
        new Table(array_map(static fn (string $part): array => explode(',', $part), $parts)),
        new PrintPage($parts[1] ?? ''),
        new Heading($input, 4),
    ];

    foreach (['fr', 'en'] as $language) {
        $book = $exporter->layout(new Document($blocks, $language));

        foreach ($book->pages() as $page) {
            if (count($page->rows) > $format->linesPerPage) {
                throw new LogicException('A page holds more lines than the format.');
            }

            foreach ($page->rows as $row) {
                if (!Braille::isBraille($row) || Braille::length($row) > $format->cellsPerLine) {
                    throw new LogicException('A row is not braille, or wider than the line.');
                }
            }
        }

        $brf->write($book);
    }
});
$config->setMaxLen(1024);

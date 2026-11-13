<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\LargePrint;
use Phil\PagedMedia\Template\LargePrintBlock;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.large_print`: `blocks` (each a `heading` or a `paragraph`), and `size`, `heading_size`,
 * `page_numbers` and `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class LargePrintTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.large_print';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $blocks = array_map(static function (Parameters $block): LargePrintBlock {
            $heading = $block->optionalString('heading');

            return null === $heading ? LargePrintBlock::paragraph($block->string('paragraph')) : LargePrintBlock::heading($heading);
        }, $read->list('blocks'));

        return new LargePrint(
            fonts: $this->fonts,
            size: $read->float('size', LargePrint::SMALLEST),
            headingSize: $read->float('heading_size', 24),
            pageNumbers: $read->bool('page_numbers', true),
            metadata: $read->metadata(),
        )->render($blocks);
    }
}

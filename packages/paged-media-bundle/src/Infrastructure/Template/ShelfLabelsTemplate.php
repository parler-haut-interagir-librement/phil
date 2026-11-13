<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\ShelfLabel;
use Phil\PagedMedia\Template\ShelfLabels;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.shelf_labels`: `labels` (each `name`, `price`, `unit_price`, `sku`, `location`), and `sheet` and
 * `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class ShelfLabelsTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.shelf_labels';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $labels = array_map(static fn (Parameters $label): ShelfLabel => new ShelfLabel($label->string('name'), $label->string('price'), $label->optionalString('unit_price'), $label->optionalString('sku'), $label->optionalString('location')), $read->list('labels'));

        return new ShelfLabels($read->sheet(LabelSheet::letter10()), $this->fonts, $read->metadata())->render($labels);
    }
}

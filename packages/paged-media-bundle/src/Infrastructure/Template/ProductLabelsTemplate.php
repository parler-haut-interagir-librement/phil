<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\ProductLabel;
use Phil\PagedMedia\Template\ProductLabels;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.product_labels`: `labels` (each `name`, `price`, `sku`, `detail`), and `sheet` and `title` when
 * wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class ProductLabelsTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.product_labels';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $labels = array_map(static fn (Parameters $label): ProductLabel => new ProductLabel($label->string('name'), $label->string('price'), $label->optionalString('sku'), $label->optionalString('detail')), $read->list('labels'));

        return new ProductLabels($read->sheet(LabelSheet::letter30()), $this->fonts, $read->metadata())->render($labels);
    }
}

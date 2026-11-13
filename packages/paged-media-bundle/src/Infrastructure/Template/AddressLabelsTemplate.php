<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\AddressLabel;
use Phil\PagedMedia\Template\AddressLabels;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.address_labels`: `labels` (each `lines`, a list of strings), and `size`, `sheet` and `title` when
 * wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class AddressLabelsTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.address_labels';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $labels = array_map(static fn (Parameters $label): AddressLabel => new AddressLabel($label->strings('lines')), $read->list('labels'));

        return new AddressLabels($read->sheet(LabelSheet::letter30()), $this->fonts, $read->float('size', 10), $read->metadata())->render($labels);
    }
}

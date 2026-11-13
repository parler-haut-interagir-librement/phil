<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\DownloadCard;
use Phil\PagedMedia\Template\DownloadCards;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.download_cards`: `instructions`, `address`, `cards` (each `title`, `code`, `subtitle`), and
 * `cut_lines`, `sheet` and `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class DownloadCardsTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.download_cards';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $cards = array_map(static fn (Parameters $card): DownloadCard => new DownloadCard($card->string('title'), $card->string('code'), $card->optionalString('subtitle')), $read->list('cards'));

        return new DownloadCards($read->string('instructions'), $read->string('address'), $read->sheet(LabelSheet::letter10()), $this->fonts, cutLines: $read->bool('cut_lines', true), metadata: $read->metadata())->render($cards);
    }
}

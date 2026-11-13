<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\GiftCard;
use Phil\PagedMedia\Template\GiftCards;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.gift_cards`: `card_title`, `cards` (each `amount`, `code`, `pin`, `recipient`, `message`,
 * `valid_until`), and `code_label`, `pin_label`, `valid_until_label`, `cut_lines`, `sheet` and `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class GiftCardsTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.gift_cards';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $cards = array_map(static fn (Parameters $card): GiftCard => new GiftCard(
            $card->string('amount'),
            $card->string('code'),
            $card->optionalString('pin'),
            $card->optionalString('recipient'),
            $card->optionalString('message'),
            $card->optionalString('valid_until'),
        ), $read->list('cards'));

        return new GiftCards(
            $read->string('card_title'),
            $read->sheet(LabelSheet::letter10()),
            $this->fonts,
            codeLabel: $read->optionalString('code_label') ?? 'Code',
            pinLabel: $read->optionalString('pin_label') ?? 'PIN',
            validUntilLabel: $read->optionalString('valid_until_label') ?? 'Valid until',
            cutLines: $read->bool('cut_lines', true),
            metadata: $read->metadata(),
        )->render($cards);
    }
}

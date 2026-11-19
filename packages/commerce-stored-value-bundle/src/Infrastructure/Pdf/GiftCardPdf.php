<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Infrastructure\Pdf;

use DateTimeImmutable;
use Phil\CommerceStoredValueBundle\Application\IssuedGiftCard;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * A gift card just issued, printed on one page the size of a payment card: the shop, its value, its code, and its
 * expiry when it has one — or that it is for a specific service. The code is known only while the card is just issued,
 * so the card is printed then, or never.
 *
 * The labels are English by default; an application printing in another language passes its own. Helvetica prints the
 * Western languages; a shop whose name needs any other script passes a TrueType font and its bold.
 */
final readonly class GiftCardPdf
{
    public const array LABELS = [
        'gift_card' => 'Gift card',
        'valid_until' => 'Valid until %s',
        'specific_service' => 'For a specific service',
    ];

    /** The width of a payment card, ID-1, in millimetres. */
    public const float WIDTH = 85.6;

    /** The height of a payment card, ID-1, in millimetres. */
    public const float HEIGHT = 53.98;

    private const float MARGIN = 12;

    /** @var array<string, string> */
    private array $labels;

    private PageSize $size;

    /**
     * @param string                $shop     the name printed on the card
     * @param array<string, string> $labels   `LABELS`' keys, translated; any other is unused
     * @param bool                  $compress whether the page's content is compressed
     */
    public function __construct(
        private string $shop,
        array $labels = [],
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
        private bool $compress = true,
    ) {
        if ('' === trim($shop)) {
            throw InvalidValue::because('A gift card is printed with the name of its shop.');
        }

        $this->labels = array_replace(self::LABELS, $labels);
        $this->size = PageSize::ofMillimetres(self::WIDTH, self::HEIGHT);
    }

    public function render(IssuedGiftCard $issued): string
    {
        $card = $issued->card;
        $pdf = new Document($this->size, new Metadata($this->labels['gift_card'], $this->shop, createdAt: $card->openedAt), $this->compress);
        $pdf->addPage();

        $width = $this->size->width;
        $height = $this->size->height;
        $right = $width - self::MARGIN;
        $pdf->rectangle(4, 4, $width - 8, $height - 8, stroke: Color::gray(160), lineWidth: 0.5);

        $title = $this->labels['gift_card'];
        $room = $right - self::MARGIN - $this->bold->width($title, 7) - 6;
        $pdf->text(self::MARGIN, $height - 22, Document::wrap($this->shop, $room, 10, $this->bold)[0], 10, $this->bold);
        $pdf->textRight($right, $height - 22, $title, 7, $this->font, Color::gray(90));

        $value = $card->balance;
        $pdf->text(self::MARGIN, $height / 2 - 2, $value->toDecimal().' '.$value->currency->code, 18, $this->bold);
        $pdf->text(self::MARGIN, 26, $issued->code->toString(), 11, $this->bold);

        $footer = [];

        if ($card->specificService) {
            $footer[] = $this->labels['specific_service'];
        }

        if ($card->expiresAt instanceof DateTimeImmutable) {
            $footer[] = \sprintf($this->labels['valid_until'], $card->expiresAt->format('Y-m-d'));
        }

        if ([] !== $footer) {
            $pdf->text(self::MARGIN, 13, implode(' - ', $footer), 7, $this->font, Color::gray(90));
        }

        return $pdf->render();
    }
}

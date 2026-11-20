<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\PagedMedia;

use Override;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * `ReturnSlipRenderer` on the native writer of `phil/paged-media`: a Letter page — more when the lines need them —
 * with the slip's number, the order and the return, the tracking number of the label, then each line's description,
 * units and reason, and what to do with the slip. Its words are the keys `phil_commerce_returns.slip.*` and
 * `phil_commerce_returns.reason.*` of the domain `phil_commerce_returns`, in the locale given, or in English without
 * a translator. It holds no state between two slips.
 */
final readonly class PagedMediaReturnSlips implements ReturnSlipRenderer
{
    private const float MARGIN = 54;

    private const float SIZE = 11;

    private const float LEADING = 16;

    /** The words without a translator. */
    private const array ENGLISH = [
        'phil_commerce_returns.slip.title' => 'Return slip',
        'phil_commerce_returns.slip.number' => 'Slip',
        'phil_commerce_returns.slip.order' => 'Order',
        'phil_commerce_returns.slip.return' => 'Return',
        'phil_commerce_returns.slip.printed' => 'Printed on',
        'phil_commerce_returns.slip.tracking' => 'Tracking',
        'phil_commerce_returns.slip.item' => 'Item',
        'phil_commerce_returns.slip.quantity' => 'Units',
        'phil_commerce_returns.slip.reason' => 'Reason',
        'phil_commerce_returns.slip.instructions' => 'Put this slip in the parcel with the items listed above.',
        'phil_commerce_returns.reason.defective' => 'Defective',
        'phil_commerce_returns.reason.damaged' => 'Damaged',
        'phil_commerce_returns.reason.wrong_item' => 'Wrong item',
        'phil_commerce_returns.reason.not_as_described' => 'Not as described',
        'phil_commerce_returns.reason.changed_mind' => 'Changed mind',
        'phil_commerce_returns.reason.other' => 'Other',
    ];

    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    #[Override]
    public function render(ReturnSlip $slip, ?string $locale = null): string
    {
        $page = PageSize::letter();
        $document = new Document($page, new Metadata(title: $this->word('phil_commerce_returns.slip.title', $locale).' '.$slip->number, createdAt: $slip->printedAt));
        $document->addPage();

        $right = $page->width - self::MARGIN;
        $y = $page->height - self::MARGIN - 20;

        $document->text(self::MARGIN, $y, $this->word('phil_commerce_returns.slip.title', $locale), 20, StandardFont::HelveticaBold);
        $y -= 2 * self::LEADING;

        $facts = [
            'phil_commerce_returns.slip.number' => $slip->number,
            'phil_commerce_returns.slip.order' => $slip->orderNumber,
            'phil_commerce_returns.slip.return' => $slip->returnId,
            'phil_commerce_returns.slip.printed' => $slip->printedAt->format('Y-m-d'),
        ];

        if ($slip->label instanceof ReturnLabel) {
            $facts['phil_commerce_returns.slip.tracking'] = $slip->label->carrier.' '.$slip->label->tracking;
        }

        foreach ($facts as $key => $value) {
            $document->text(self::MARGIN, $y, $this->word($key, $locale), self::SIZE, StandardFont::HelveticaBold);
            $document->text(self::MARGIN + 110, $y, $value, self::SIZE);
            $y -= self::LEADING;
        }

        $y -= self::LEADING;
        $y = $this->header($document, $y, $right, $locale);
        $quantityAt = $right - 140;
        $width = $quantityAt - self::MARGIN - 12;

        foreach ($slip->lines as $line) {
            $wrapped = Document::wrap($line['description'], $width, self::SIZE);

            if ($y - \count($wrapped) * self::LEADING < self::MARGIN + 2 * self::LEADING) {
                $document->addPage();
                $y = $this->header($document, $page->height - self::MARGIN - self::SIZE, $right, $locale);
            }

            $document->textRight($quantityAt + 30, $y, (string) $line['quantity'], self::SIZE);
            $document->text($quantityAt + 50, $y, $this->word('phil_commerce_returns.reason.'.$line['reason']->value, $locale), self::SIZE);

            foreach ($wrapped as $text) {
                $document->text(self::MARGIN, $y, $text, self::SIZE);
                $y -= self::LEADING;
            }
        }

        $document->text(self::MARGIN, max(self::MARGIN, $y - self::LEADING), $this->word('phil_commerce_returns.slip.instructions', $locale), self::SIZE);

        return $document->render();
    }

    private function header(Document $document, float $y, float $right, ?string $locale): float
    {
        $quantityAt = $right - 140;
        $document->text(self::MARGIN, $y, $this->word('phil_commerce_returns.slip.item', $locale), self::SIZE, StandardFont::HelveticaBold);
        $document->textRight($quantityAt + 30, $y, $this->word('phil_commerce_returns.slip.quantity', $locale), self::SIZE, StandardFont::HelveticaBold);
        $document->text($quantityAt + 50, $y, $this->word('phil_commerce_returns.slip.reason', $locale), self::SIZE, StandardFont::HelveticaBold);
        $document->line(self::MARGIN, $y - 4, $right, $y - 4, 0.5);

        return $y - self::LEADING - 4;
    }

    private function word(string $key, ?string $locale): string
    {
        return $this->translator?->trans($key, [], 'phil_commerce_returns', $locale) ?? self::ENGLISH[$key] ?? $key;
    }
}

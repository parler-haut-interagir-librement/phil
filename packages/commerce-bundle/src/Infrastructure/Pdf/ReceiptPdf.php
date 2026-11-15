<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Pdf;

use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\Money\Money;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * A receipt or a credit note as a PDF invoice: the issuer and its registration numbers, the document's number and
 * date, the order, each line with its options below it — the option's label and its value, or that it is drawn
 * after payment —, the adjustments other than taxes, each tax by its code, and the total. Amounts are
 * written in the receipt's currency, named once; a credit note's are negative. A description too long for its column
 * goes on over the lines below, never over the amounts. The taxes the prices included come
 * after the total, each by its code and then their sum: the buyer sees what was paid in taxes, never added twice.
 *
 * The labels are English by default; an application printing in another language passes its own. Helvetica prints
 * the Western languages; an application whose names or labels need any other script passes a TrueType font and its
 * bold, which the document embeds. A receipt kept for the years the law asks is printed as PDF/A-2u, which needs those
 * fonts: Helvetica is not embedded. The document carries the receipt's title, its issuer and its date.
 */
final readonly class ReceiptPdf
{
    public const array LABELS = [
        'receipt' => 'Receipt',
        'credit_note' => 'Credit note',
        'date' => 'Date',
        'order' => 'Order',
        'credits' => 'Credits',
        'currency' => 'Amounts in',
        'description' => 'Description',
        'quantity' => 'Qty',
        'unit_price' => 'Unit price',
        'amount' => 'Amount',
        'subtotal' => 'Subtotal',
        'total' => 'Total',
        'included' => '%s included',
        'taxes_included' => 'Taxes included',
        'option' => '%s: %s',
        'to_draw' => 'drawn after payment',
    ];

    private const float MARGIN = 54;

    private const float LINE = 14;

    /** Where the quantity column's header starts, from the right margin. */
    private const float QUANTITY_COLUMN = 225;

    /** The space kept between a description and the quantity column. */
    private const float GUTTER = 10;

    /** How far the options of a line stand in from its description. */
    private const float INDENT = 12;

    /** @var array<string, string> */
    private array $labels;

    private PageSize $size;

    /**
     * @param array<string, string> $labels      `LABELS`' keys, translated; any other is unused
     * @param PageSize|null         $size        the page; Letter when none is given
     * @param Conformance|null      $conformance PDF/A-2u for an archived receipt, with TrueType fonts
     */
    public function __construct(
        array $labels = [],
        ?PageSize $size = null,
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
        private ?Conformance $conformance = null,
    ) {
        $this->labels = array_replace(self::LABELS, $labels);
        $this->size = $size ?? PageSize::letter();
    }

    public function render(Receipt $receipt): string
    {
        $title = $this->labels[DocumentKind::CreditNote === $receipt->kind ? 'credit_note' : 'receipt'].' '.$receipt->number;
        $pdf = new Document($this->size, new Metadata($title, $receipt->issuer->name, createdAt: $receipt->issuedAt), conformance: $this->conformance);
        $pdf->addPage();

        $right = $this->size->width - self::MARGIN;
        $y = $this->size->height - self::MARGIN;
        $credit = DocumentKind::CreditNote === $receipt->kind;

        $pdf->text(self::MARGIN, $y, $receipt->issuer->name, 16, $this->bold);

        foreach ([...$receipt->issuer->address, ...array_map(static fn (string $code, string $number): string => $code.' '.$number, array_keys($receipt->issuer->taxNumbers), $receipt->issuer->taxNumbers)] as $line) {
            $y -= self::LINE;
            $pdf->text(self::MARGIN, $y, $line, 10, $this->font);
        }

        $y -= 2 * self::LINE;
        $pdf->text(self::MARGIN, $y, $this->labels[$credit ? 'credit_note' : 'receipt'].' '.$receipt->number, 14, $this->bold);
        $details = [
            $this->labels['date'].' '.$receipt->issuedAt->format('Y-m-d'),
            $this->labels['order'].' '.$receipt->orderNumber,
            ...(null === $receipt->creditedReceipt ? [] : [$this->labels['credits'].' '.$receipt->creditedReceipt]),
            $this->labels['currency'].' '.$receipt->currency->code,
        ];

        foreach ($details as $detail) {
            $y -= self::LINE;
            $pdf->text(self::MARGIN, $y, $detail, 10, $this->font);
        }

        $y -= 2 * self::LINE;
        $this->header($pdf, $y, $right);

        foreach ($receipt->lines() as $line) {
            [$first, $rest] = $this->description($line->description, $right);
            $y = $this->room($pdf, $y, $right);
            $pdf->text(self::MARGIN, $y, $first, 10, $this->font);
            $pdf->textRight($right - 200, $y, (string) $line->quantity, 10, $this->font);
            $pdf->textRight($right - 100, $y, $line->unitPrice->toDecimal(), 10, $this->font);
            $pdf->textRight($right, $y, $this->signed($line->subtotal(), $credit)->toDecimal(), 10, $this->font);

            foreach ($rest as $continued) {
                $y = $this->room($pdf, $y, $right);
                $pdf->text(self::MARGIN, $y, $continued, 10, $this->font);
            }

            foreach ($line->configuration->values as $option) {
                $text = \sprintf($this->labels['option'], $option->label, $option->shown() ?? $this->labels['to_draw']);

                foreach (Document::wrap($text, $right - self::QUANTITY_COLUMN - self::GUTTER - self::MARGIN - self::INDENT, 10, $this->font) as $optionLine) {
                    $y = $this->room($pdf, $y, $right);
                    $pdf->text(self::MARGIN + self::INDENT, $y, $optionLine, 10, $this->font);
                }
            }
        }

        $y = $this->room($pdf, $y - self::LINE / 2, $right);
        $pdf->text($right - 250, $y, $this->labels['subtotal'], 10, $this->font);
        $pdf->textRight($right, $y, $receipt->itemsTotal()->toDecimal(), 10, $this->font);

        foreach ($receipt->adjustments() as $adjustment) {
            if (AdjustmentType::Tax === $adjustment->type || $adjustment->neutral) {
                continue;
            }

            $y = $this->room($pdf, $y, $right);
            $pdf->text($right - 250, $y, $adjustment->label, 10, $this->font);
            $pdf->textRight($right, $y, $this->signed($adjustment->amount, $credit)->toDecimal(), 10, $this->font);
        }

        $included = $receipt->includedTaxes();

        foreach (array_diff_key($receipt->taxes(), $included) as $code => $amount) {
            $y = $this->room($pdf, $y, $right);
            $pdf->text($right - 250, $y, $code, 10, $this->font);
            $pdf->textRight($right, $y, $amount->toDecimal(), 10, $this->font);
        }

        $y = $this->room($pdf, $y - self::LINE / 2, $right);
        $pdf->text($right - 250, $y, $this->labels['total'], 12, $this->bold);
        $pdf->textRight($right, $y, $receipt->total()->toDecimal(), 12, $this->bold);

        if ([] !== $included) {
            $y -= self::LINE / 2;

            foreach ($included as $code => $amount) {
                $y = $this->room($pdf, $y, $right);
                $pdf->text($right - 250, $y, \sprintf($this->labels['included'], $code), 10, $this->font);
                $pdf->textRight($right, $y, $amount->toDecimal(), 10, $this->font);
            }

            $y = $this->room($pdf, $y, $right);
            $pdf->text($right - 250, $y, $this->labels['taxes_included'], 10, $this->font);
            $pdf->textRight($right, $y, Money::sum(...array_values($included))->toDecimal(), 10, $this->font);
        }

        return $pdf->render();
    }

    /**
     * The next line's baseline, on a new page with the table's header when this one is full.
     */
    private function room(Document $pdf, float $y, float $right): float
    {
        $y -= self::LINE;

        if ($y >= self::MARGIN) {
            return $y;
        }

        $pdf->addPage();
        $y = $this->size->height - self::MARGIN;
        $this->header($pdf, $y, $right);

        return $y - self::LINE;
    }

    /**
     * A line's description cut to the width left of the quantity column: its first line, printed beside the
     * amounts, and the lines that follow it, printed below.
     *
     * @return array{string, list<string>}
     */
    private function description(string $description, float $right): array
    {
        $lines = Document::wrap($description, $right - self::QUANTITY_COLUMN - self::GUTTER - self::MARGIN, 10, $this->font);

        return [array_shift($lines), $lines];
    }

    private function header(Document $pdf, float $y, float $right): void
    {
        $pdf->text(self::MARGIN, $y, $this->labels['description'], 10, $this->bold);
        $pdf->text($right - self::QUANTITY_COLUMN, $y, $this->labels['quantity'], 10, $this->bold);
        $pdf->text($right - 150, $y, $this->labels['unit_price'], 10, $this->bold);
        $pdf->text($right - 45, $y, $this->labels['amount'], 10, $this->bold);
    }

    private function signed(Money $amount, bool $credit): Money
    {
        return $credit ? $amount->negate() : $amount;
    }
}

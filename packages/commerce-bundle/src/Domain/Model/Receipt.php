<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\ReceiptRefused;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A receipt or a credit note: what was sold or given back, as it stood when issued, never edited afterwards.
 *
 * - **A receipt copies a placed order**: its lines, its adjustments and its issuer, so that a later change of a
 *   price, a rate or an address never reaches a receipt already issued.
 * - **A credit note reverses a receipt**, rather than the receipt being edited: in whole, or for some of its lines,
 *   each with its own adjustments. The order's own adjustments — shipping, fees — go with the note that credits the
 *   last line left. Its amounts are those of the receipt, counted negatively.
 * - **The number is given by the series of its kind**, sequential and without gaps.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_receipt')]
#[ORM\Index(columns: ['order_number'])]
#[ORM\Index(columns: ['credited_receipt'])]
final class Receipt
{
    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    public Issuer $issuer {
        get => Issuer::fromArray($this->issuerData);
    }

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    /** @var array{name: string, address: list<string>, tax_numbers: array<string, string>} */
    #[ORM\Column(name: 'issuer', type: 'json')]
    private array $issuerData;

    /**
     * @param array<string, OrderLine> $lines
     * @param list<Adjustment>         $adjustments
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'number', length: 32)]
        public readonly string $number,
        #[ORM\Column(name: 'kind', length: 16, enumType: DocumentKind::class)]
        public readonly DocumentKind $kind,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'credited_receipt', length: 32, nullable: true)]
        public readonly ?string $creditedReceipt,
        #[ORM\Column(name: 'issued_at')]
        public readonly DateTimeImmutable $issuedAt,
        Currency $currency,
        Issuer $issuer,
        #[ORM\Column(name: '`lines`', type: 'phil_commerce_order_lines')]
        private array $lines,
        #[ORM\Column(name: 'adjustments', type: 'phil_commerce_adjustments')]
        private array $adjustments,
    ) {
        $this->currencyCode = $currency->code;
        $this->issuerData = $issuer->toArray();
    }

    public static function issue(string $number, Order $order, Issuer $issuer, DateTimeImmutable $at): self
    {
        if (OrderState::Placed !== $order->state()) {
            throw ReceiptRefused::orderNotPlaced($order->number);
        }

        $lines = [];

        foreach ($order->lines() as $line) {
            $lines[$line->reference->value()] = $line;
        }

        return new self($number, DocumentKind::Receipt, $order->number, null, $at, $order->currency, $issuer, $lines, $order->adjustments());
    }

    /**
     * A credit note of the lines named, or of every line not credited yet; the receipt's issuer is kept.
     *
     * @param list<Receipt>                $creditNotes the receipt's credit notes issued before
     * @param list<SellableReference>|null $lines       the lines to credit, whole; every line left by default
     */
    public static function credit(string $number, self $receipt, array $creditNotes, DateTimeImmutable $at, ?array $lines = null): self
    {
        if (DocumentKind::Receipt !== $receipt->kind) {
            throw ReceiptRefused::notAReceipt($receipt->number);
        }

        $left = $receipt->lines;

        foreach ($creditNotes as $note) {
            foreach (array_keys($note->lines) as $credited) {
                unset($left[$credited]);
            }
        }

        $chosen = [];

        foreach ($lines ?? array_map(static fn (OrderLine $line): SellableReference => $line->reference, $left) as $reference) {
            $chosen[$reference->value()] = $left[$reference->value()] ?? throw ReceiptRefused::lineNotCreditable($receipt->number, $reference->value());
        }

        if ([] === $chosen) {
            throw ReceiptRefused::nothingToCredit($receipt->number);
        }

        $last = \count($chosen) === \count($left);
        $adjustments = [];

        foreach ($receipt->adjustments as $adjustment) {
            if (null === $adjustment->line ? $last : isset($chosen[$adjustment->line->value()])) {
                $adjustments[] = $adjustment;
            }
        }

        return new self($number, DocumentKind::CreditNote, $receipt->orderNumber, $receipt->number, $at, $receipt->currency, $receipt->issuer, $chosen, $adjustments);
    }

    /**
     * @return list<OrderLine> the lines as they were sold, quantities and prices positive
     */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    /**
     * @return list<Adjustment> the adjustments as they were on the order
     */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * The lines' subtotals, negative on a credit note.
     */
    public function itemsTotal(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            $total = $total->add($line->subtotal());
        }

        return $this->signed($total);
    }

    /**
     * The adjustments that are not neutral, of every type or of one, negative on a credit note.
     */
    public function adjustmentsTotal(?AdjustmentType $type = null): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->adjustments as $adjustment) {
            if (!$adjustment->neutral && (!$type instanceof AdjustmentType || $adjustment->type === $type)) {
                $total = $total->add($adjustment->amount);
            }
        }

        return $this->signed($total);
    }

    /**
     * Each tax as the receipt itemises it, summed over the lines by its code, negative on a credit note. A tax
     * already included in the prices is shown too.
     *
     * @return array<string, Money> rate code → amount, in the order the codes first appear
     */
    public function taxes(): array
    {
        $taxes = [];

        foreach ($this->adjustments as $adjustment) {
            if (AdjustmentType::Tax === $adjustment->type) {
                $taxes[$adjustment->label] = ($taxes[$adjustment->label] ?? Money::zero($this->currency))->add($adjustment->amount);
            }
        }

        return array_map($this->signed(...), $taxes);
    }

    /**
     * The taxes the prices included, by rate code, negative on a credit note: what the buyer paid in taxes without
     * their being added to the total. Empty when the taxes were added.
     *
     * @return array<string, Money> rate code → amount, in the order the codes first appear
     */
    public function includedTaxes(): array
    {
        $taxes = [];

        foreach ($this->adjustments as $adjustment) {
            if (AdjustmentType::Tax === $adjustment->type && $adjustment->neutral) {
                $taxes[$adjustment->label] = ($taxes[$adjustment->label] ?? Money::zero($this->currency))->add($adjustment->amount);
            }
        }

        return array_map($this->signed(...), $taxes);
    }

    public function total(): Money
    {
        return $this->itemsTotal()->add($this->adjustmentsTotal());
    }

    private function signed(Money $amount): Money
    {
        return DocumentKind::CreditNote === $this->kind ? $amount->negate() : $amount;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Commerce;

use Closure;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\Money\Money;
use Phil\ReceiptEmail\Application\Port\PaymentReceiptSource;
use Phil\ReceiptEmail\Domain\Payment\PaymentDocument;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Domain\ValueObject\PaymentReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptCharge;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptLine;
use Phil\ReceiptEmail\Domain\ValueObject\TaxAmount;

/**
 * Reads a receipt or a credit note of `phil/commerce-bundle` as it was issued: its lines, each with the taxes charged
 * on it and its options, its other adjustments, its taxes summed by rate code, its subtotal and its total, every
 * amount taken from the receipt and signed as the receipt signs it — negative on a credit note.
 *
 * An option is shown as `label: value`; one still to draw is listed by its label among the line's pending options. A
 * neutral adjustment other than a tax is left out, as the receipt's PDF leaves it out.
 */
final readonly class CommercePaymentReceipts implements PaymentReceiptSource
{
    public function __construct(
        private ReceiptRepository $receipts,
    ) {
    }

    public function find(string $number): ?PaymentReceipt
    {
        $receipt = $this->receipts->find($number);

        return $receipt instanceof Receipt ? self::read($receipt) : null;
    }

    public static function read(Receipt $receipt): PaymentReceipt
    {
        $credit = DocumentKind::CreditNote === $receipt->kind;
        $signed = static fn (Money $amount): Money => $credit ? $amount->negate() : $amount;
        $lines = array_map(static fn (OrderLine $line): ReceiptLine => self::line($line, $receipt->adjustments(), $signed), $receipt->lines());
        $charges = [];

        foreach ($receipt->adjustments() as $adjustment) {
            if (AdjustmentType::Tax !== $adjustment->type && !$adjustment->neutral) {
                $charges[] = ReceiptCharge::of($adjustment->label, $signed($adjustment->amount));
            }
        }

        $included = $receipt->includedTaxes();
        $taxes = [];

        foreach ($receipt->taxes() as $code => $amount) {
            $taxes[] = TaxAmount::of($code, $amount, isset($included[$code]));
        }

        return PaymentReceipt::of(
            $credit ? PaymentDocument::CreditNote : PaymentDocument::Receipt,
            $receipt->number,
            $receipt->orderNumber,
            $receipt->creditedReceipt,
            $receipt->issuedAt,
            Party::of($receipt->issuer->name, $receipt->issuer->address),
            $receipt->issuer->taxNumbers,
            $lines,
            $charges,
            $taxes,
            $receipt->itemsTotal(),
            $receipt->total(),
        );
    }

    /**
     * @param list<Adjustment>      $adjustments
     * @param Closure(Money): Money $signed
     */
    private static function line(OrderLine $line, array $adjustments, Closure $signed): ReceiptLine
    {
        $taxes = [];

        foreach ($adjustments as $adjustment) {
            if (AdjustmentType::Tax === $adjustment->type && null !== $adjustment->line && $adjustment->line->equals($line->reference)) {
                $taxes[] = TaxAmount::of($adjustment->label, $signed($adjustment->amount), $adjustment->neutral);
            }
        }

        $options = [];
        $pending = [];

        foreach ($line->configuration->values as $code => $option) {
            $label = $option->label ?? $code;
            $shown = $option->shown();

            if (null === $shown) {
                $pending[] = $label;
            } else {
                $options[] = $label.': '.$shown;
            }
        }

        return ReceiptLine::of($line->description, $line->quantity, $line->unitPrice, $signed($line->subtotal()), $taxes, $options, $pending);
    }
}

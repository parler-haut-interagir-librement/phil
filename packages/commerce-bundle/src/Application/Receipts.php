<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Domain\Exception\ReceiptRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * Issues the receipt of a placed order, once, and the credit notes that reverse it, each numbered in its series.
 *
 * The application calls it inside a transaction and commits it with the document: a number taken is never lost,
 * and two documents never share one.
 *
 * What a document depends on — no receipt yet for the order, the lines a credit note may still credit — is checked
 * before a number is taken, so a refused document never uses one, and checked again once the number is taken.
 * Taking a number locks its series until the transaction ends, so two issues for the same order run one after the
 * other: the second one reads the first one's document and is refused, and its transaction rolls back with the
 * number it took.
 */
final readonly class Receipts
{
    private Issuer $issuer;

    /**
     * @param array{name: string, address: list<string>, tax_numbers: array<string, string>} $issuer as
     *                                                                                               `phil_commerce.receipts.issuer` holds it
     */
    public function __construct(
        private DocumentNumbering $numbering,
        private ReceiptRepository $receipts,
        array $issuer,
    ) {
        $this->issuer = Issuer::fromArray($issuer);
    }

    public function issue(Order $order, DateTimeImmutable $at): Receipt
    {
        $this->checkNotIssued($order);

        if (OrderState::Placed !== $order->state()) {
            throw ReceiptRefused::orderNotPlaced($order->number);
        }

        $number = $this->numbering->next(DocumentKind::Receipt);
        // Read again under the series' lock: a receipt another transaction committed meanwhile is seen now.
        $this->checkNotIssued($order);

        $receipt = Receipt::issue($number, $order, $this->issuer, $at);
        $this->receipts->add($receipt);

        return $receipt;
    }

    /**
     * @param list<SellableReference>|null $lines the lines to credit, whole; every line left by default
     */
    public function credit(Receipt $receipt, DateTimeImmutable $at, ?array $lines = null): Receipt
    {
        $notes = $this->receipts->creditNotesOf($receipt->number);

        // Checked before a number is taken, so a refused note never uses one.
        Receipt::credit('check', $receipt, $notes, $at, $lines);

        $number = $this->numbering->next(DocumentKind::CreditNote);
        // Read again under the series' lock: a note another transaction committed meanwhile is counted now.
        $note = Receipt::credit($number, $receipt, $this->receipts->creditNotesOf($receipt->number), $at, $lines);
        $this->receipts->add($note);

        return $note;
    }

    private function checkNotIssued(Order $order): void
    {
        $existing = $this->receipts->receiptOf($order->number);

        if ($existing instanceof Receipt) {
            throw ReceiptRefused::alreadyIssued($order->number, $existing->number);
        }
    }
}

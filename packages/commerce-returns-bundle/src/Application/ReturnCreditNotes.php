<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\Repository\ReceiptRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnCreditNoteIssued;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Issues the credit note of a return once its goods came back: part of a line, a restocking fee, a special's
 * discount and the delivery charge included, which the core's credit notes, made of whole lines, cannot say.
 *
 * Its number comes from the core's series of credit notes, so the receipts' credit notes stay one sequence without
 * gaps. The application calls it inside a transaction and commits it with the note: what the note depends on — no
 * note yet for the return, no line credited whole by the core — is checked before the number is taken and again
 * once it is, under the series' lock.
 */
final readonly class ReturnCreditNotes
{
    public function __construct(
        private ReturnRepository $returns,
        private ReceiptRepository $receipts,
        private DocumentNumbering $numbering,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function issue(string $returnId, DateTimeImmutable $at): ReturnCreditNote
    {
        $return = $this->returns->findReturn($returnId) ?? throw ReturnRefused::unknown($returnId);

        if (!\in_array($return->state(), [ReturnState::Received, ReturnState::Resolved], true)) {
            throw ReturnRefused::notSettled($return->id, $return->state());
        }

        $receipt = $this->receipts->receiptOf($return->orderNumber) ?? throw ReturnRefused::noReceipt($return->orderNumber);
        // Checked before a number is taken, so a refused note never uses one.
        $this->check($return, $receipt);
        $number = $this->numbering->next(DocumentKind::CreditNote);
        // Read again under the series' lock: a note another transaction committed meanwhile is counted now.
        $this->check($return, $receipt);

        $note = ReturnCreditNote::issue($number, $return, $receipt->number, $this->lines($return, $receipt), $at);
        $this->returns->addCreditNote($note);
        $this->dispatcher?->dispatch(new ReturnCreditNoteIssued($note->number, $return->id, $return->orderNumber, $note->total(), $at));

        return $note;
    }

    private function check(ReturnRequest $return, Receipt $receipt): void
    {
        $existing = $this->returns->findCreditNote($return->id);

        if ($existing instanceof ReturnCreditNote) {
            throw ReturnRefused::alreadyCredited($return->id, $existing->number);
        }

        $credited = [];

        foreach ($this->receipts->creditNotesOf($receipt->number) as $note) {
            foreach ($note->lines() as $line) {
                $credited[$line->reference->value()] = $note->number;
            }
        }

        foreach ($return->lines() as $line) {
            if (isset($credited[$line->line])) {
                throw ReturnRefused::creditedWhole($line->line, $credited[$line->line]);
            }
        }
    }

    /**
     * Each line that came back, described as the receipt sold it, with its units' share of the line's taxes.
     *
     * @return list<array{line: string, description: string, quantity: int, line_quantity: int, amount: int, taxes: int, fee: int}>
     */
    private function lines(ReturnRequest $return, Receipt $receipt): array
    {
        $sold = [];

        foreach ($receipt->lines() as $orderLine) {
            $sold[$orderLine->reference->value()] = $orderLine;
        }

        $taxes = [];

        foreach ($receipt->adjustments() as $adjustment) {
            if (AdjustmentType::Tax === $adjustment->type && !$adjustment->neutral && null !== $adjustment->line) {
                $taxes[$adjustment->line->value()] = ($taxes[$adjustment->line->value()] ?? 0) + $adjustment->amount->minorUnits;
            }
        }

        $lines = [];

        foreach ($return->lines() as $line) {
            if (ItemCondition::Missing === $line->condition) {
                continue;
            }

            $tax = $taxes[$line->line] ?? 0;
            $share = static fn (int $units): int => intdiv(2 * $tax * $units + $line->lineQuantity, 2 * $line->lineQuantity);
            $orderLine = $sold[$line->line] ?? null;

            $lines[] = [
                'line' => $line->line,
                'description' => $orderLine instanceof OrderLine ? $orderLine->description : $line->line,
                'quantity' => $line->quantity,
                'line_quantity' => $line->lineQuantity,
                'amount' => $line->value(),
                'taxes' => $share($line->returnedBefore + $line->quantity) - $share($line->returnedBefore),
                'fee' => $return->feeOn($line),
            ];
        }

        return $lines;
    }
}

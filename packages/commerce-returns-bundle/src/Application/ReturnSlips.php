<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;

/**
 * The slip a customer puts in the parcel of an authorized return, as a PDF in the order's language.
 *
 * The first print numbers the slip in the series `return_slip` of the core's document register, sequential and
 * without gaps: the application calls it inside a transaction and commits it with the number. A later print keeps
 * the number.
 */
final readonly class ReturnSlips
{
    public function __construct(
        private ReturnRepository $returns,
        private OrderRepository $orders,
        private DocumentRegister $register,
        private ReturnSlipRenderer $renderer,
    ) {
    }

    /**
     * What the slip of an authorized return says, numbered at its first print.
     */
    public function slipFor(string $returnId, DateTimeImmutable $at): ReturnSlip
    {
        $return = $this->returns->findReturn($returnId) ?? throw ReturnRefused::unknown($returnId);

        if (ReturnState::Authorized !== $return->state()) {
            throw ReturnRefused::notAuthorized($return->id, $return->state());
        }

        $number = $return->slipNumber();

        if (null === $number) {
            $number = $this->register->next(ReturnDocumentTypes::RETURN_SLIP);
            $return->numberSlip($number);
        }

        return new ReturnSlip($number, $return->id, $return->orderNumber, $at, $this->lines($return), $return->label());
    }

    /**
     * The slip drawn as a PDF, in the locale given.
     *
     * @return string the PDF's bytes
     */
    public function print(string $returnId, DateTimeImmutable $at, ?string $locale = null): string
    {
        return $this->renderer->render($this->slipFor($returnId, $at), $locale);
    }

    /**
     * @return list<array{line: string, description: string, quantity: int, reason: ReturnReason}>
     */
    private function lines(ReturnRequest $return): array
    {
        $order = $this->orders->find($return->orderNumber);
        $descriptions = [];

        if ($order instanceof Order) {
            foreach ($order->lines() as $orderLine) {
                $descriptions[$orderLine->reference->value()] = $orderLine->description;
            }
        }

        $lines = [];

        foreach ($return->lines() as $line) {
            $lines[] = ['line' => $line->line, 'description' => $descriptions[$line->line] ?? $line->line, 'quantity' => $line->quantity, 'reason' => $line->reason];
        }

        return $lines;
    }
}

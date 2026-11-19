<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The credit note of a return: what it gives back of a receipt, line by line, a line's units in part included, as it
 * stood when issued, never edited afterwards. Its number comes from the core's series of credit notes, sequential and
 * without gaps; its amounts are positive, what is given back.
 *
 * Each line gives its units' share of what the order line came to — taxes included, and the tax they hold apart —,
 * less its restocking fee; then the discounts of the specials the return breaks come off, and the delivery charge
 * given back comes on. Units that never came back are not credited.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_return_credit_note')]
#[ORM\UniqueConstraint(name: 'phil_commerce_return_credit_note_return', columns: ['return_id'])]
#[ORM\Index(columns: ['order_number'])]
final class ReturnCreditNote
{
    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    /**
     * @param list<array{line: string, description: string, quantity: int, line_quantity: int, amount: int, taxes: int, fee: int}> $lines
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'number', length: 32)]
        public readonly string $number,
        #[ORM\Column(name: 'return_id', length: 36, options: ['fixed' => true])]
        public readonly string $returnId,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'receipt_number', length: 32)]
        public readonly string $receiptNumber,
        #[ORM\Column(name: 'issued_at')]
        public readonly DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'lines', type: 'json')]
        private readonly array $lines,
        #[ORM\Column(name: 'special_discount', type: 'bigint')]
        private readonly int $specialDiscount,
        #[ORM\Column(name: 'delivery', type: 'bigint')]
        private readonly int $delivery,
    ) {
    }

    /**
     * @param list<array{line: string, description: string, quantity: int, line_quantity: int, amount: int, taxes: int, fee: int}> $lines
     */
    public static function issue(string $number, ReturnRequest $return, string $receiptNumber, array $lines, DateTimeImmutable $at): self
    {
        $credited = 0;

        foreach ($lines as $line) {
            $credited += $line['amount'] - $line['fee'];
        }

        $special = min(array_sum($return->specialCharges()), max(0, $credited));

        return new self($number, $return->id, $return->orderNumber, $receiptNumber, $at, $return->currency()->code, $lines, $special, $return->deliveryRefund()->minorUnits);
    }

    /**
     * @return list<array{line: string, description: string, quantity: int, line_quantity: int, amount: Money, taxes: Money, fee: Money}>
     */
    public function lines(): array
    {
        $currency = $this->currency;

        return array_map(static fn (array $line): array => [...$line, 'amount' => Money::of($line['amount'], $currency), 'taxes' => Money::of($line['taxes'], $currency), 'fee' => Money::of($line['fee'], $currency)], $this->lines);
    }

    /**
     * The discounts of the specials the return breaks, taken off.
     */
    public function specialDiscount(): Money
    {
        return Money::of($this->specialDiscount, $this->currency);
    }

    /**
     * The delivery charge given back.
     */
    public function delivery(): Money
    {
        return Money::of($this->delivery, $this->currency);
    }

    /**
     * What the note gives back: its lines less their fees, less the specials' discounts, plus the delivery.
     */
    public function total(): Money
    {
        $total = -$this->specialDiscount + $this->delivery;

        foreach ($this->lines as $line) {
            $total += $line['amount'] - $line['fee'];
        }

        return Money::of($total, $this->currency);
    }
}

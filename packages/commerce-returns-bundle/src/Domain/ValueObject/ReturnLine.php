<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\ValueObject;

use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One line of a return: which order line, how many units, why, and — once received — in what condition. It keeps
 * what the order line came to, adjustments on it included, how many units it held and how many earlier returns
 * already took: the value of the units returned is their share of it, so that the returns of one line, each rounded,
 * add up to what the line came to.
 */
final readonly class ReturnLine
{
    private function __construct(
        public string $line,
        public int $quantity,
        public ReturnReason $reason,
        public int $linePaid,
        public int $lineQuantity,
        public int $returnedBefore,
        public ?ItemCondition $condition,
    ) {
    }

    /**
     * @param string $line           the order line, as `SellableReference::value()` gives it: `product:TEA-1`, or
     *                               `product.fingerprint:SHIRT` for a configured line
     * @param int    $linePaid       what the whole order line came to, in minor units, its promotions and taxes included
     * @param int    $returnedBefore the units of the line that earlier returns of the order still hold
     */
    public static function of(string $line, int $quantity, ReturnReason $reason, int $linePaid, int $lineQuantity, int $returnedBefore = 0): self
    {
        if ($returnedBefore < 0 || $returnedBefore >= $lineQuantity) {
            throw InvalidValue::because(\sprintf('Earlier returns of %s took 0 to %d units; %d is refused.', $line, $lineQuantity - 1, $returnedBefore));
        }

        if ($quantity < 1 || $quantity > $lineQuantity - $returnedBefore) {
            throw InvalidValue::because(\sprintf('A return line takes back 1 to %d units of %s; %d is refused.', $lineQuantity - $returnedBefore, $line, $quantity));
        }

        if ($linePaid < 0) {
            throw InvalidValue::because(\sprintf('The line %s came to zero or more.', $line));
        }

        return new self($line, $quantity, $reason, $linePaid, $lineQuantity, $returnedBefore, null);
    }

    public function receivedAs(ItemCondition $condition): self
    {
        return new self($this->line, $this->quantity, $this->reason, $this->linePaid, $this->lineQuantity, $this->returnedBefore, $condition);
    }

    /**
     * The value of the units returned: the share of the line up to and including them, less the share of the units
     * earlier returns took, each to the nearest minor unit, halves up. The returns of a whole line add up to what it
     * came to, to the minor unit.
     */
    public function value(): int
    {
        return $this->share($this->returnedBefore + $this->quantity) - $this->share($this->returnedBefore);
    }

    /**
     * @return array{line: string, quantity: int, reason: string, line_paid: int, line_quantity: int, returned_before: int, condition: ?string}
     */
    public function toArray(): array
    {
        return ['line' => $this->line, 'quantity' => $this->quantity, 'reason' => $this->reason->value, 'line_paid' => $this->linePaid, 'line_quantity' => $this->lineQuantity, 'returned_before' => $this->returnedBefore, 'condition' => $this->condition?->value];
    }

    /**
     * @param array{line: string, quantity: int, reason: string, line_paid: int, line_quantity: int, returned_before?: int, condition: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        $line = self::of($data['line'], $data['quantity'], ReturnReason::from($data['reason']), $data['line_paid'], $data['line_quantity'], $data['returned_before'] ?? 0);

        return null === $data['condition'] ? $line : $line->receivedAs(ItemCondition::from($data['condition']));
    }

    /**
     * The share of what the line came to that its first units hold, to the nearest minor unit, halves up.
     */
    private function share(int $units): int
    {
        return intdiv(2 * $this->linePaid * $units + $this->lineQuantity, 2 * $this->lineQuantity);
    }
}

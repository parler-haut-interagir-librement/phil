<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A return of goods — an RMA —: the order it comes from, its lines, and its way from the request to its resolution.
 *
 *     requested → authorized → received → resolved
 *     requested, authorized → awaiting information → requested
 *     requested, authorized → rejected, cancelled
 *     authorized → expired
 *
 * Its value is that of its lines, less the restocking fee of the lines the shop is not at fault for, less the
 * discounts of the specials it breaks, plus the delivery charge it gives back.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_return')]
#[ORM\Index(columns: ['order_number'])]
final class ReturnRequest
{
    /** @var list<array{line: string, quantity: int, reason: string, line_paid: int, line_quantity: int, condition: ?string}> */
    #[ORM\Column(name: 'lines', type: 'json')]
    private array $lines;

    #[ORM\Column(name: 'state', length: 16, enumType: ReturnState::class)]
    private ReturnState $state = ReturnState::Requested;

    #[ORM\Column(name: 'resolution', length: 16, nullable: true, enumType: Resolution::class)]
    private ?Resolution $resolution = null;

    #[ORM\Column(name: 'note', length: 255, nullable: true)]
    private ?string $note = null;

    /** @var array<string, int> the discount of each special the return breaks, by its origin, in minor units */
    #[ORM\Column(name: 'special_charges', type: 'json')]
    private array $specialCharges = [];

    /** The delivery charge given back with the return, in minor units. */
    #[ORM\Column(name: 'delivery_refund', type: 'bigint')]
    private int $deliveryRefund = 0;

    /** @var array{carrier: string, tracking: string, document: ?string}|null */
    #[ORM\Column(name: 'label', type: 'json', nullable: true)]
    private ?array $label = null;

    #[ORM\Column(name: 'slip_number', length: 32, nullable: true)]
    private ?string $slipNumber = null;

    #[ORM\Column(name: 'changed_at')]
    private DateTimeImmutable $changedAt;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    /**
     * @param list<ReturnLine> $lines
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'restocking_fee', type: 'integer')]
        public readonly int $restockingFee,
        #[ORM\Column(name: 'requested_at')]
        public readonly DateTimeImmutable $requestedAt,
        array $lines,
    ) {
        $this->lines = array_map(static fn (ReturnLine $line): array => $line->toArray(), $lines);
        $this->changedAt = $requestedAt;
    }

    /**
     * @param list<ReturnLine>   $lines
     * @param int                $restockingFee  the share kept on the lines the shop is not at fault for, in parts per million
     * @param array<string, int> $specialCharges the discount of each special the return breaks, by its origin, in minor units
     * @param int                $deliveryRefund the delivery charge given back, in minor units
     */
    public static function request(string $orderNumber, Currency $currency, array $lines, int $restockingFee, DateTimeImmutable $at, array $specialCharges = [], int $deliveryRefund = 0): self
    {
        if ([] === $lines) {
            throw InvalidValue::because(\sprintf('A return of %s takes back at least one line.', $orderNumber));
        }

        $names = array_map(static fn (ReturnLine $line): string => $line->line, $lines);

        if (\count(array_unique($names)) !== \count($names)) {
            throw InvalidValue::because(\sprintf('A return of %s names each line once.', $orderNumber));
        }

        if ($restockingFee < 0 || $restockingFee > 1_000_000) {
            throw InvalidValue::because(\sprintf('A restocking fee is 0 to 1000000 parts per million; %d is refused.', $restockingFee));
        }

        foreach ($specialCharges as $origin => $charge) {
            if ($charge < 1 || 1 !== preg_match('/^[\x21-\x7E]{1,255}$/D', $origin)) {
                throw InvalidValue::because(\sprintf('A return of %s takes back the discount of a special named by its origin, more than zero.', $orderNumber));
            }
        }

        if ($deliveryRefund < 0) {
            throw InvalidValue::because(\sprintf('A return of %s gives back a delivery charge of zero or more.', $orderNumber));
        }

        $return = new self(Uuid::v7()->toRfc4122(), $orderNumber, $currency->code, $restockingFee, $at, $lines);
        $return->specialCharges = $specialCharges;
        $return->deliveryRefund = $deliveryRefund;

        return $return;
    }

    /**
     * @return list<ReturnLine>
     */
    public function lines(): array
    {
        return array_map(ReturnLine::fromArray(...), $this->lines);
    }

    public function state(): ReturnState
    {
        return $this->state;
    }

    public function resolution(): ?Resolution
    {
        return $this->resolution;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function changedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    /**
     * @return array<string, int> the discount of each special the return breaks, by its origin, in minor units
     */
    public function specialCharges(): array
    {
        return $this->specialCharges;
    }

    /**
     * The delivery charge given back with the return.
     */
    public function deliveryRefund(): Money
    {
        return Money::of($this->deliveryRefund, $this->currency());
    }

    public function label(): ?ReturnLabel
    {
        return null === $this->label ? null : ReturnLabel::fromArray($this->label);
    }

    /**
     * The number of the return slip, once one is printed.
     */
    public function slipNumber(): ?string
    {
        return $this->slipNumber;
    }

    /**
     * How many units of an order line this return holds.
     */
    public function quantityOf(string $line): int
    {
        return array_find($this->lines(), static fn (ReturnLine $returned): bool => $returned->line === $line)->quantity ?? 0;
    }

    /**
     * What the return is worth: its lines, less the restocking fee on the lines the shop is not at fault for, nothing
     * for the units that never came back, less the discounts of the specials it breaks — never below zero —, plus the
     * delivery charge it gives back.
     */
    public function value(): Money
    {
        $value = 0;

        foreach ($this->lines() as $line) {
            if (ItemCondition::Missing !== $line->condition) {
                $value += $line->value() - $this->feeOn($line);
            }
        }

        return Money::of(max(0, $value - array_sum($this->specialCharges)) + $this->deliveryRefund, $this->currency());
    }

    /**
     * The restocking fee kept on a line: none when the shop is at fault.
     */
    public function feeOn(ReturnLine $line): int
    {
        return $line->reason->isShopsFault() ? 0 : intdiv($line->value() * $this->restockingFee + 500_000, 1_000_000);
    }

    public function authorize(DateTimeImmutable $at): void
    {
        $this->move([ReturnState::Requested], ReturnState::Authorized, $at);
    }

    public function reject(string $note, DateTimeImmutable $at): void
    {
        $note = $this->checkedNote($note, 'A rejection says why, in 1 to 255 characters.');
        $this->move([ReturnState::Requested, ReturnState::AwaitingInformation, ReturnState::Authorized], ReturnState::Rejected, $at);
        $this->note = $note;
    }

    public function cancel(DateTimeImmutable $at): void
    {
        $this->move([ReturnState::Requested, ReturnState::AwaitingInformation, ReturnState::Authorized], ReturnState::Cancelled, $at);
    }

    /**
     * Sends the return back to the customer for what the shop needs to know, said in the note.
     */
    public function askForInformation(string $note, DateTimeImmutable $at): void
    {
        $note = $this->checkedNote($note, 'A question to the customer says what the shop needs, in 1 to 255 characters.');
        $this->move([ReturnState::Requested, ReturnState::Authorized], ReturnState::AwaitingInformation, $at);
        $this->note = $note;
    }

    /**
     * The customer gave what the shop asked: the return is requested again.
     */
    public function provideInformation(DateTimeImmutable $at): void
    {
        $this->move([ReturnState::AwaitingInformation], ReturnState::Requested, $at);
    }

    /**
     * The goods of an authorized return never came back in time: its units are free again.
     */
    public function expire(DateTimeImmutable $at): void
    {
        $this->move([ReturnState::Authorized], ReturnState::Expired, $at);
    }

    /**
     * Keeps the label a carrier gave to send the goods of an authorized return back.
     */
    public function attachLabel(ReturnLabel $label): void
    {
        if (ReturnState::Authorized !== $this->state) {
            throw ReturnRefused::notAuthorized($this->id, $this->state);
        }

        $this->label = $label->toArray();
    }

    /**
     * Keeps the number of its return slip, given once.
     */
    public function numberSlip(string $number): void
    {
        if (null !== $this->slipNumber) {
            throw InvalidValue::because(\sprintf('The return %s already has the slip %s.', $this->id, $this->slipNumber));
        }

        $this->slipNumber = $number;
    }

    /**
     * Erases what was written freely — the note of a rejection or of a question —, keeping what the accounts need.
     */
    public function anonymize(): void
    {
        $this->note = null;
    }

    /**
     * Records what each line was found to be.
     *
     * @param array<string, ItemCondition> $conditions by order line; every line of the return needs one
     */
    public function receive(array $conditions, DateTimeImmutable $at): void
    {
        $received = [];

        foreach ($this->lines() as $line) {
            $condition = $conditions[$line->line] ?? throw ReturnRefused::conditionMissing($this->id, $line->line);
            $received[] = $line->receivedAs($condition)->toArray();
        }

        $this->move([ReturnState::Authorized], ReturnState::Received, $at);
        $this->lines = $received;
    }

    public function resolve(Resolution $resolution, DateTimeImmutable $at): void
    {
        $this->move([ReturnState::Received], ReturnState::Resolved, $at);
        $this->resolution = $resolution;
    }

    /**
     * @param list<ReturnState> $from
     */
    private function move(array $from, ReturnState $to, DateTimeImmutable $at): void
    {
        if (!\in_array($this->state, $from, true)) {
            throw ReturnRefused::transition($this->id, $this->state, $to);
        }

        $this->state = $to;
        $this->changedAt = $at;
    }

    private function checkedNote(string $note, string $rule): string
    {
        if ('' === trim($note) || mb_strlen($note) > 255) {
            throw InvalidValue::because($rule);
        }

        return $note;
    }
}

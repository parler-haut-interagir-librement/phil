<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Change\OrderChangeState;
use Phil\CommerceBundle\Domain\Exception\OrderChangeRefused;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A change of a placed order, prepared, then confirmed or abandoned: the lines it sets, the order's total before and
 * as the preview gives it, and once confirmed, the revision it made and the total the order ended with.
 *
 * The difference due is what the confirmed change added to the order's total — negative when it lowered it —, which
 * the order's payments then cover or hold beyond its total.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_order_change')]
#[ORM\Index(columns: ['order_number', 'state'])]
final class OrderChange
{
    public Money $totalBefore {
        get => Money::of($this->totalBeforeMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $totalAfter {
        get => Money::of($this->totalAfterMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'state', length: 16, enumType: OrderChangeState::class)]
    private OrderChangeState $state = OrderChangeState::Prepared;

    #[ORM\Column(name: 'revision', type: 'integer', nullable: true)]
    private ?int $revision = null;

    #[ORM\Column(name: 'confirmed_by', length: 255, nullable: true)]
    private ?string $confirmedBy = null;

    #[ORM\Column(name: 'settled_at', nullable: true)]
    private ?DateTimeImmutable $settledAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    /**
     * @param list<array{reference: string, quantity: int}> $lines
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        /** The order's revision the change was prepared on: a change prepared on another one is refused. */
        #[ORM\Column(name: 'base_revision', type: 'integer')]
        public readonly int $baseRevision,
        #[ORM\Column(name: '`lines`', type: 'json')]
        private array $lines,
        #[ORM\Column(name: 'reason', length: 255)]
        public readonly string $reason,
        #[ORM\Column(name: 'prepared_by', length: 255)]
        public readonly string $preparedBy,
        #[ORM\Column(name: 'prepared_at')]
        public readonly DateTimeImmutable $preparedAt,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'total_before', type: 'bigint')]
        private readonly int $totalBeforeMinorUnits,
        #[ORM\Column(name: 'total_after', type: 'bigint')]
        private int $totalAfterMinorUnits,
    ) {
    }

    /**
     * @param list<OrderChangeLine> $lines   one per reference
     * @param Money                 $preview the order's total as the change would leave it
     */
    public static function prepare(Order $order, array $lines, string $reason, string $preparedBy, DateTimeImmutable $at, Money $preview): self
    {
        if ([] === $lines || \count($lines) > 500) {
            throw InvalidValue::because('A change sets 1 to 500 lines.');
        }

        $stored = [];

        foreach ($lines as $line) {
            if (isset($stored[$line->reference->value()])) {
                throw InvalidValue::because(\sprintf('A change sets %s once.', $line->reference->value()));
            }

            $stored[$line->reference->value()] = ['reference' => $line->reference->value(), 'quantity' => $line->quantity];
        }

        if ('' === trim($reason) || mb_strlen($reason) > 255 || 1 !== preg_match('/^\P{Cc}+$/Du', $reason)) {
            throw InvalidValue::because('A change has a reason: 1 to 255 characters on one line.');
        }

        return new self(Uuid::v7()->toRfc4122(), $order->number, $order->revision(), array_values($stored), $reason, Identifier::check($preparedBy, 'actor'), $at, $order->currency->code, $order->total()->minorUnits, $preview->minorUnits);
    }

    public function state(): OrderChangeState
    {
        return $this->state;
    }

    /**
     * @return list<OrderChangeLine>
     */
    public function lines(): array
    {
        return array_map(static fn (array $line): OrderChangeLine => OrderChangeLine::of(SellableReference::fromValue($line['reference']), $line['quantity']), $this->lines);
    }

    /**
     * What the change adds to the order's total: the preview's while prepared, the order's once confirmed.
     */
    public function difference(): Money
    {
        return $this->totalAfter->subtract($this->totalBefore);
    }

    /**
     * The order's revision the change made; `null` until confirmed.
     */
    public function revision(): ?int
    {
        return $this->revision;
    }

    public function confirmedBy(): ?string
    {
        return $this->confirmedBy;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Records the change as applied: the order's new revision and the total it ended with.
     */
    public function confirm(int $revision, Money $total, string $confirmedBy, DateTimeImmutable $at): void
    {
        $this->checkPrepared();
        $this->state = OrderChangeState::Confirmed;
        $this->revision = $revision;
        $this->totalAfterMinorUnits = $total->minorUnits;
        $this->confirmedBy = Identifier::check($confirmedBy, 'actor');
        $this->settledAt = $at;
    }

    public function abandon(DateTimeImmutable $at): void
    {
        $this->checkPrepared();
        $this->state = OrderChangeState::Abandoned;
        $this->settledAt = $at;
    }

    private function checkPrepared(): void
    {
        if (OrderChangeState::Prepared !== $this->state) {
            throw OrderChangeRefused::notPrepared($this->id, $this->state);
        }
    }
}

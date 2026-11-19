<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseState;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Goods ordered from a supplier, to be received at one location: how many of each SKU were ordered, how many came,
 * and when the rest is expected. Each receipt against it goes to the stock journal under its number.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_purchase_order')]
#[ORM\Index(columns: ['state'])]
final class PurchaseOrder
{
    /** The most SKUs one purchase order lists. */
    public const int MAX_LINES = 500;

    #[ORM\Column(name: 'state', length: 16, enumType: PurchaseState::class)]
    private PurchaseState $state = PurchaseState::Open;

    /** @var array<int|string, int> SKU → units received; a numeric SKU is an integer key, as PHP makes it */
    #[ORM\Column(name: 'received', type: 'json')]
    private array $received = [];

    /**
     * @param array<int|string, int> $ordered SKU → units ordered; a numeric SKU is an integer key, as PHP makes it
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'number', length: 64)]
        public readonly string $number,
        #[ORM\Column(name: 'supplier', length: 255)]
        public readonly string $supplier,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'ordered', type: 'json')]
        public readonly array $ordered,
        #[ORM\Column(name: 'ordered_at')]
        public readonly DateTimeImmutable $orderedAt,
        #[ORM\Column(name: 'expected_on', type: 'date_immutable', nullable: true)]
        private ?DateTimeImmutable $expectedOn,
    ) {
    }

    /**
     * @param array<int|string, int> $lines SKU → units ordered, each one or more; a numeric SKU is an integer key
     */
    public static function of(string $number, string $supplier, Location $location, array $lines, DateTimeImmutable $at, ?DateTimeImmutable $expectedOn = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $number)) {
            throw InvalidValue::because(\sprintf('"%s" is not a purchase order number: 1 to 64 visible ASCII characters.', $number));
        }

        if ('' === trim($supplier) || mb_strlen($supplier) > 255) {
            throw InvalidValue::because(\sprintf('The purchase order %s names its supplier in 1 to 255 characters.', $number));
        }

        if ([] === $lines || \count($lines) > self::MAX_LINES) {
            throw InvalidValue::because(\sprintf('The purchase order %s lists 1 to %d SKUs.', $number, self::MAX_LINES));
        }

        foreach ($lines as $sku => $quantity) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', (string) $sku) || $quantity < 1) {
                throw InvalidValue::because(\sprintf('The purchase order %s orders one unit or more of each SKU, each 1 to 64 visible ASCII characters.', $number));
            }
        }

        return new self($number, $supplier, $location->code, $lines, $at, $expectedOn?->setTime(0, 0));
    }

    public function state(): PurchaseState
    {
        return $this->state;
    }

    public function expectedOn(): ?DateTimeImmutable
    {
        return $this->expectedOn;
    }

    public function expectOn(?DateTimeImmutable $day): void
    {
        $this->expectedOn = $day?->setTime(0, 0);
    }

    public function received(string $sku): int
    {
        return $this->received[$sku] ?? 0;
    }

    /**
     * The units of a SKU still expected; none once the order is no longer open.
     */
    public function outstanding(string $sku): int
    {
        return PurchaseState::Open === $this->state ? ($this->ordered[$sku] ?? 0) - $this->received($sku) : 0;
    }

    /**
     * Records units that came; once every unit ordered came, the order is received.
     */
    public function receive(string $sku, int $quantity): void
    {
        if (PurchaseState::Open !== $this->state) {
            throw PurchaseRefused::notOpen($this->number);
        }

        if (!isset($this->ordered[$sku])) {
            throw PurchaseRefused::notOrdered($this->number, $sku);
        }

        $outstanding = $this->outstanding($sku);

        if ($quantity < 1 || $quantity > $outstanding) {
            throw PurchaseRefused::overReceived($this->number, $sku, $quantity, $outstanding);
        }

        $this->received[$sku] = $this->received($sku) + $quantity;

        foreach (array_keys($this->ordered) as $ordered) {
            if ($this->outstanding((string) $ordered) > 0) {
                return;
            }
        }

        $this->state = PurchaseState::Received;
    }

    /**
     * Expects nothing more: the supplier will not send the rest.
     */
    public function close(): void
    {
        if (PurchaseState::Open !== $this->state) {
            throw PurchaseRefused::notOpen($this->number);
        }

        $this->state = PurchaseState::Closed;
    }
}

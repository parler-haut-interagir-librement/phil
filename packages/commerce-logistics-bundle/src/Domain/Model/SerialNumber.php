<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Serial\SerialState;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One unit of a SKU known by its serial number: where it is kept, and the order it left with.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_serial_number')]
#[ORM\Index(columns: ['sku', 'location', 'state'])]
#[ORM\Index(columns: ['order_number'])]
final class SerialNumber
{
    #[ORM\Column(name: 'state', length: 16, enumType: SerialState::class)]
    private SerialState $state = SerialState::InStock;

    #[ORM\Column(name: 'order_number', length: 64, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\Column(name: 'shipped_at', nullable: true)]
    private ?DateTimeImmutable $shippedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Id]
        #[ORM\Column(name: 'serial', length: 128)]
        public readonly string $serial,
        #[ORM\Column(name: 'location', length: 64)]
        private string $location,
        #[ORM\Column(name: 'received_at')]
        public readonly DateTimeImmutable $receivedAt,
    ) {
    }

    /**
     * @param string $serial 1 to 128 visible ASCII characters
     */
    public static function of(StockLevel $level, string $serial, DateTimeImmutable $at): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $serial)) {
            throw InvalidValue::because('A serial number is 1 to 128 visible ASCII characters.');
        }

        return new self($level->sku, $serial, $level->location, $at);
    }

    public function state(): SerialState
    {
        return $this->state;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function orderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function shippedAt(): ?DateTimeImmutable
    {
        return $this->shippedAt;
    }

    /**
     * The unit moved to another location with a transfer.
     */
    public function move(StockLevel $level): void
    {
        $this->checkInStock();
        $this->location = $level->location;
    }

    public function ship(string $orderNumber, DateTimeImmutable $at): void
    {
        $this->checkInStock();
        $this->state = SerialState::Shipped;
        $this->orderNumber = $orderNumber;
        $this->shippedAt = $at;
    }

    /**
     * The unit came back — a return — and is on a shelf again.
     */
    public function restock(StockLevel $level): void
    {
        if (SerialState::Shipped !== $this->state) {
            throw StockRefused::serialInStock($this->sku, $this->serial);
        }

        $this->state = SerialState::InStock;
        $this->location = $level->location;
        $this->orderNumber = null;
        $this->shippedAt = null;
    }

    private function checkInStock(): void
    {
        if (SerialState::InStock !== $this->state) {
            throw StockRefused::serialNotInStock($this->sku, $this->serial);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A cart parked at a register while the customer fetches something, to be picked up again by its number.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_held_sale')]
#[ORM\Index(columns: ['register'])]
final class HeldSale
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'register', length: 32)]
        public readonly string $register,
        #[ORM\Column(name: 'label', length: 64)]
        public readonly string $label,
        #[ORM\Column(name: 'held_by', length: 255)]
        public readonly string $heldBy,
        #[ORM\Column(name: 'held_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $heldAt,
    ) {
    }

    /**
     * @param string $label what the cashier calls it: the customer's name, a description
     */
    public static function of(string $orderNumber, Register $register, string $label, string $by, DateTimeImmutable $at): self
    {
        if ('' === trim($label) || mb_strlen($label) > 64) {
            throw InvalidValue::because('A held sale is labelled in 1 to 64 characters.');
        }

        return new self($orderNumber, $register->code, $label, $by, $at);
    }
}

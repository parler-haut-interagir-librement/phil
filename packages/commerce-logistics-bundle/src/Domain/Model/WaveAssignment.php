<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * An order planned in a shipping wave; an order is in one wave at most.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_wave_order')]
#[ORM\Index(columns: ['wave'])]
final class WaveAssignment
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'wave', length: 64)]
        private string $wave,
    ) {
    }

    public static function of(string $orderNumber, ShippingWave $wave): self
    {
        return new self($orderNumber, $wave->code);
    }

    public function wave(): string
    {
        return $this->wave;
    }

    public function moveTo(ShippingWave $wave): void
    {
        $this->wave = $wave->code;
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One promotion counted for one placed order, with the code that unlocked it and the customer who bought: the record
 * that makes counting a placed order's promotions happen once, however many times it is asked.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_promotion_redemption')]
#[ORM\Index(columns: ['promotion'])]
final class PromotionRedemption
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Id]
        #[ORM\Column(name: 'promotion', length: 64)]
        public readonly string $promotion,
        #[ORM\Column(name: 'code', length: 64, nullable: true)]
        public readonly ?string $code,
        #[ORM\Column(name: 'customer', length: 64, nullable: true)]
        public readonly ?string $customer,
    ) {
    }

    public static function of(string $orderNumber, string $promotion, ?string $code, ?string $customer = null): self
    {
        foreach ([$orderNumber, $promotion, $code ?? 'none', $customer ?? 'none'] as $name) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $name)) {
                throw InvalidValue::because('An order number, a promotion, a code and a customer are 1 to 64 visible ASCII characters.');
            }
        }

        return new self($orderNumber, $promotion, $code, $customer);
    }
}

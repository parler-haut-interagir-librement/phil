<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The uses one customer made of one promotion, counted against its limit per customer: one row per promotion and
 * customer, created at the first use.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_promotion_customer_use')]
final class PromotionCustomerUse
{
    #[ORM\Column(name: 'uses', type: 'integer')]
    private int $uses = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'promotion', length: 64)]
        public readonly string $promotion,
        #[ORM\Id]
        #[ORM\Column(name: 'customer', length: 64)]
        public readonly string $customer,
    ) {
    }

    public static function of(string $promotion, string $customer): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $promotion) || 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $customer)) {
            throw InvalidValue::because('A promotion and a customer are 1 to 64 visible ASCII characters.');
        }

        return new self($promotion, $customer);
    }

    public function uses(): int
    {
        return $this->uses;
    }

    /**
     * Counts one use under the limit: `false`, and nothing counted, when the customer reached it. The Doctrine adapter
     * of `PromotionRepository` claims the same way in one atomic `UPDATE`.
     */
    public function claim(int $limit): bool
    {
        if ($this->uses >= $limit) {
            return false;
        }

        ++$this->uses;

        return true;
    }
}

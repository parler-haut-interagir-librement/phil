<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A buyer's place in a customer group. The buyer is named as the application names it: an account, or the tenant of
 * a multi-tenant application — a shop buying wholesale for its own customers.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wholesale_membership')]
#[ORM\Index(columns: ['customer_group'])]
final class Membership
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'buyer', length: 255)]
        public readonly string $buyer,
        #[ORM\Column(name: 'customer_group', length: 64)]
        private string $group,
    ) {
    }

    public static function of(string $buyer, CustomerGroup $group): self
    {
        if ('' === trim($buyer) || \strlen($buyer) > 255) {
            throw InvalidValue::because('A buyer is named by 1 to 255 characters.');
        }

        return new self($buyer, $group->code);
    }

    public function group(): string
    {
        return $this->group;
    }

    public function moveTo(CustomerGroup $group): void
    {
        $this->group = $group->code;
    }
}

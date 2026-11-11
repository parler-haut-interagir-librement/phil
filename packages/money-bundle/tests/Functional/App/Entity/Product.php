<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A product with a price embedded in two columns and the currency its supplier bills in.
 */
#[ORM\Entity]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Embedded(class: Money::class)]
        public Money $price,
        #[ORM\Column(type: 'phil_currency', nullable: true)]
        public ?Currency $supplierCurrency = null,
    ) {
    }
}

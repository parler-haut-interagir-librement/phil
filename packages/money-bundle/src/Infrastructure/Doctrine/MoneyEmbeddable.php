<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;

/**
 * The ORM mapping of `Phil\Money\Money`, in attributes: two columns, the amount in minor units and the currency code,
 * so that a query can sum, sort and filter amounts of one currency. An embedded `price` maps to `price_amount` and
 * `price_currency`.
 *
 * `phil/money` stays free of Doctrine, so the attributes live on this class, which mirrors the properties of `Money`;
 * `MoneyMappingDriver` reads them for `Money`. It is never instantiated.
 *
 * @internal
 */
#[ORM\Embeddable]
final readonly class MoneyEmbeddable
{
    private function __construct(
        #[ORM\Column(name: 'amount', type: Types::BIGINT)]
        public int $minorUnits,
        #[ORM\Column(name: 'currency', type: CurrencyType::NAME)]
        public Currency $currency,
    ) {
    }
}

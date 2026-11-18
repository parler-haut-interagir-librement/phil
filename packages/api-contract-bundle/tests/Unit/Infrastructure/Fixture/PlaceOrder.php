<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture;

/**
 * A message that carries the identifier of an order, and nothing else.
 */
final readonly class PlaceOrder
{
    public function __construct(
        public string $order,
    ) {
    }
}

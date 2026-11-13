<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

/**
 * An event of a site.
 */
final readonly class OrderPaid
{
    public function __construct(
        public string $order,
        public string $customer,
        public string $plan = 'basic',
    ) {
    }
}

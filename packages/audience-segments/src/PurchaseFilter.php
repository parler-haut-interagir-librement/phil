<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

/**
 * The parts of an order a `purchase.made` condition looks for: one of the SKUs, one of the products, one of the order
 * states. An empty part matches any order.
 */
final readonly class PurchaseFilter
{
    /**
     * @param list<string> $skus
     * @param list<string> $products
     * @param list<string> $orderStates
     */
    public function __construct(
        public array $skus = [],
        public array $products = [],
        public array $orderStates = [],
    ) {
    }
}

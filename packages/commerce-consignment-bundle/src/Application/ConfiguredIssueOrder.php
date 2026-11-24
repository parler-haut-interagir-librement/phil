<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\IssueOrderPolicy;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;

/**
 * One issue order for every SKU, and another for the SKUs named: `issue_order` and `issue_order_by_sku` of the
 * bundle's configuration.
 */
final readonly class ConfiguredIssueOrder implements IssueOrderPolicy
{
    /**
     * @param array<string, IssueOrder> $bySku
     */
    public function __construct(
        private IssueOrder $default = IssueOrder::OldestFirst,
        private array $bySku = [],
    ) {
    }

    /**
     * @param array<array-key, string> $bySku SKU → the value of an issue order, as the configuration writes them
     */
    public static function fromConfiguration(string $default, array $bySku): self
    {
        $orders = [];

        foreach ($bySku as $sku => $order) {
            $orders[(string) $sku] = IssueOrder::from($order);
        }

        return new self(IssueOrder::from($default), $orders);
    }

    #[Override]
    public function issueOrder(string $sku): IssueOrder
    {
        return $this->bySku[$sku] ?? $this->default;
    }
}

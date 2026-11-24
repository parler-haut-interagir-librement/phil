<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use DateTimeImmutable;
use Phil\InventoryAlerts\Contract\StockLevelSource;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Stock\StockLevel;

/**
 * Proposes purchase orders: one per supplier, a line for each SKU and location at or under its reorder point. It
 * keeps no state, and never orders.
 */
final readonly class ReplenishmentPlanner
{
    public const int MAX_RULES = 100_000;

    public function __construct(private StockLevelSource $stock)
    {
    }

    /**
     * @param iterable<ReplenishmentRule> $rules at most 100,000, one per SKU and location
     *
     * @return list<PurchaseProposal> by supplier in the order of their first rule, lines by location then SKU
     */
    public function propose(iterable $rules, DateTimeImmutable $at): array
    {
        $lines = [];
        $suppliers = [];
        $seen = [];
        $count = 0;

        foreach ($rules as $rule) {
            if (++$count > self::MAX_RULES) {
                throw InvalidInput::because('list of rules', 'must have at most 100,000 rules');
            }

            $key = $rule->sku."\0".$rule->location;

            if (isset($seen[$key])) {
                throw InvalidInput::because('list of rules', \sprintf('must have one rule per SKU and location; "%s" at "%s" has two', $rule->sku, $rule->location));
            }

            $seen[$key] = true;
            $level = $this->stock->level($rule->sku, $rule->location) ?? StockLevel::empty($rule->sku, $rule->location);
            $position = $level->position();
            $quantity = $rule->quantityFor($position);

            if ($quantity > 0) {
                $suppliers[$rule->supplier] = $rule->supplier;
                $lines[$rule->supplier][] = new ProposalLine($rule->sku, $rule->location, $quantity, $position, $rule->reorderPoint);
            }
        }

        $proposals = [];

        foreach ($lines as $supplier => $supplierLines) {
            usort($supplierLines, static fn (ProposalLine $a, ProposalLine $b): int => [$a->location, $a->sku] <=> [$b->location, $b->sku]);
            $proposals[] = new PurchaseProposal($suppliers[$supplier], $supplierLines, $at);
        }

        return $proposals;
    }
}

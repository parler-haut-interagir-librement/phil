<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Application;

use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Domain\Repository\QuantityRuleRepository;

/**
 * The quantity rules of SKUs, per customer group: a group's own rule comes before the rule for every group, and a SKU
 * without either takes any quantity.
 */
final readonly class QuantityRules
{
    public function __construct(
        private QuantityRuleRepository $rules,
    ) {
    }

    /**
     * Sets the rule of the SKU for the group, or for every group when `$customerGroup` is null, or changes it.
     */
    public function set(string $sku, ?string $customerGroup, int $minimum = 1, ?int $maximum = null, int $increment = 1): QuantityRule
    {
        $rule = $this->rules->findRule($sku, $customerGroup);

        if ($rule instanceof QuantityRule) {
            $rule->change($minimum, $maximum, $increment);

            return $rule;
        }

        $rule = QuantityRule::of($sku, $customerGroup, $minimum, $maximum, $increment);
        $this->rules->addRule($rule);

        return $rule;
    }

    public function remove(string $sku, ?string $customerGroup): void
    {
        $rule = $this->rules->findRule($sku, $customerGroup);

        if ($rule instanceof QuantityRule) {
            $this->rules->removeRule($rule);
        }
    }

    /**
     * The rule a line of the SKU follows for the group: its own, else the rule for every group; `null` when the SKU
     * has neither.
     */
    public function ruleFor(string $sku, ?string $customerGroup): ?QuantityRule
    {
        $general = null;

        foreach ($this->rules->rulesOf($sku) as $rule) {
            if (null === $rule->customerGroup) {
                $general = $rule;
            } elseif (null !== $customerGroup && $rule->customerGroup === $customerGroup) {
                return $rule;
            }
        }

        return $general;
    }
}

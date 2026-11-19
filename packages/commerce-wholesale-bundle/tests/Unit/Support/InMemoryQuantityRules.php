<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Domain\Repository\QuantityRuleRepository;

final class InMemoryQuantityRules implements QuantityRuleRepository
{
    /** @var array<string, QuantityRule> */
    public array $rules = [];

    public int $lookups = 0;

    public function __construct(QuantityRule ...$rules)
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    #[Override]
    public function findRule(string $sku, ?string $customerGroup): ?QuantityRule
    {
        return $this->rules[$sku."\0".$customerGroup] ?? null;
    }

    #[Override]
    public function rulesOf(string $sku): array
    {
        ++$this->lookups;

        return array_values(array_filter($this->rules, static fn (QuantityRule $rule): bool => $rule->sku === $sku));
    }

    #[Override]
    public function addRule(QuantityRule $rule): void
    {
        $this->rules[$rule->sku."\0".$rule->customerGroup] = $rule;
    }

    #[Override]
    public function removeRule(QuantityRule $rule): void
    {
        unset($this->rules[$rule->sku."\0".$rule->customerGroup]);
    }
}

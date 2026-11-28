<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Condition;

use Phil\AudienceSegments\Contract\Purchases;
use Phil\AudienceSegments\PurchaseFilter;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `purchase.made`: the member has an order with one of the `skus`, one of the `products` and in one of the
 * `order_states`, each part optional; without any part, any order.
 */
final readonly class PurchaseMade implements ConditionType
{
    public function __construct(private Purchases $purchases)
    {
    }

    public function name(): string
    {
        return 'purchase.made';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(
            Operand::identifierList('skus', required: false),
            Operand::identifierList('products', required: false),
            Operand::identifierList('order_states', required: false, maxItems: 20),
        );
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $filter = new PurchaseFilter(
            $operands->has('skus') ? $operands->strings('skus') : [],
            $operands->has('products') ? $operands->strings('products') : [],
            $operands->has('order_states') ? $operands->strings('order_states') : [],
        );

        return Member::verdict($this->purchases->bought($member, $filter), 'purchase.made', 'purchase.none', [
            'skus' => \count($filter->skus),
            'products' => \count($filter->products),
            'order_states' => \count($filter->orderStates),
        ]);
    }
}

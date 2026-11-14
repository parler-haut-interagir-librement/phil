<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * JsonLogic's `if`: the value of the first branch whose condition is true, else the otherwise value, else `null`;
 * `{"if": [{"<": [{"var": "age"}, 18]}, "minor", "adult"]}`.
 */
final readonly class Conditional implements Expression
{
    /**
     * @param list<array{0: Expression, 1: Expression}> $branches each a condition and its value, at least one
     *
     * @throws InvalidLogic without a branch
     */
    public function __construct(public array $branches, public ?Expression $otherwise = null)
    {
        if ([] === $branches) {
            throw InvalidLogic::because('An "if" has at least one condition and its value.');
        }
    }

    #[Override]
    public function jsonLogic(): array
    {
        return ['if' => array_map(static fn (Expression $node): array|string|int|float|bool|null => $node->jsonLogic(), $this->children())];
    }

    #[Override]
    public function children(): array
    {
        $nodes = [];

        foreach ($this->branches as [$condition, $value]) {
            $nodes[] = $condition;
            $nodes[] = $value;
        }

        if ($this->otherwise instanceof Expression) {
            $nodes[] = $this->otherwise;
        }

        return $nodes;
    }
}

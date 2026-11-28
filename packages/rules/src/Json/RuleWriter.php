<?php

declare(strict_types=1);

namespace Phil\Rules\Json;

use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Condition;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Rule;
use stdClass;

/**
 * Writes a rule in its versioned JSON form, which `RuleReader` reads back to the same rule. The operands are written
 * as their schemas keep them, in the order the schemas declare them, so that one rule always writes the same text.
 */
final readonly class RuleWriter
{
    /**
     * @return array{version: int, condition: array<string, mixed>}
     */
    public function toArray(Rule $rule): array
    {
        return ['version' => Rule::VERSION, 'condition' => $this->node($rule->condition, false)];
    }

    public function toJson(Rule $rule): string
    {
        return json_encode(
            ['version' => Rule::VERSION, 'condition' => $this->node($rule->condition, true)],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * The SHA-256 of the rule's JSON form: two rules with the same fingerprint say the same thing.
     */
    public function fingerprint(Rule $rule): string
    {
        return hash('sha256', $this->toJson($rule));
    }

    /**
     * @return array<string, mixed>
     */
    private function node(Condition $condition, bool $json): array
    {
        if ($condition instanceof All || $condition instanceof Any) {
            return [
                $condition instanceof All ? 'all' : 'any' => array_map(fn (Condition $child): array => $this->node($child, $json), $condition->conditions),
            ];
        }

        if ($condition instanceof Not) {
            return ['not' => $this->node($condition->condition, $json)];
        }

        \assert($condition instanceof Leaf);

        return ['type' => $condition->type, 'operands' => $json && [] === $condition->operands ? new stdClass() : $condition->operands];
    }
}

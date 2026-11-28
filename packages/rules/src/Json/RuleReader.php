<?php

declare(strict_types=1);

namespace Phil\Rules\Json;

use JsonException;
use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Condition;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Limits;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

/**
 * Reads the versioned JSON form of a rule, strictly: `{"version": 1, "condition": …}`, where a condition is
 * `{"all": [ … ]}`, `{"any": [ … ]}`, `{"not": { … }}` or `{"type": "…", "operands": { … }}`. Any other key, an
 * unknown version, an unknown type or a refused operand is refused, and the bounds of the limits hold while reading.
 */
final readonly class RuleReader
{
    public function __construct(
        private ConditionTypes $types,
        private Limits $limits = new Limits(),
    ) {
    }

    /**
     * @throws InvalidRule   when the JSON is malformed or the rule is refused
     * @throws LimitExceeded when the rule is larger or deeper than the limits
     */
    public function fromJson(string $json): Rule
    {
        if (\strlen($json) > $this->limits->maxBytes) {
            throw LimitExceeded::bytes(\strlen($json), $this->limits->maxBytes);
        }

        try {
            // A level of conditions takes at most two levels of JSON, an operand list two more under a leaf.
            $decoded = json_decode($json, true, 2 * $this->limits->maxDepth + 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            if (\JSON_ERROR_DEPTH === $jsonException->getCode()) {
                throw LimitExceeded::depth(Rule::ROOT, $this->limits->maxDepth);
            }

            throw InvalidRule::notJson($jsonException->getMessage());
        }

        if (!\is_array($decoded)) {
            throw InvalidRule::malformed('', 'a rule is an object with "version" and "condition"');
        }

        return $this->fromArray($decoded);
    }

    /**
     * The same form, already decoded, such as the body of an API request.
     *
     * @param array<array-key, mixed> $rule
     *
     * @throws InvalidRule   when the rule is refused
     * @throws LimitExceeded when the rule is larger or deeper than the limits
     */
    public function fromArray(array $rule): Rule
    {
        if (array_is_list($rule) || [] !== array_diff(array_keys($rule), ['version', 'condition'])) {
            throw InvalidRule::malformed('', 'a rule is an object with "version" and "condition" only');
        }

        if (!\array_key_exists('version', $rule) || Rule::VERSION !== $rule['version']) {
            throw InvalidRule::unsupportedVersion($rule['version'] ?? null);
        }

        if (!\array_key_exists('condition', $rule)) {
            throw InvalidRule::malformed('', 'the rule holds no "condition"');
        }

        $nodes = 0;

        return new Rule($this->node($rule['condition'], Rule::ROOT, 1, $nodes), $this->types, $this->limits);
    }

    private function node(mixed $node, string $path, int $depth, int &$nodes): Condition
    {
        if ($depth > $this->limits->maxDepth) {
            throw LimitExceeded::depth($path, $this->limits->maxDepth);
        }

        if (++$nodes > $this->limits->maxNodes) {
            throw LimitExceeded::nodes($this->limits->maxNodes);
        }

        if (!\is_array($node) || [] === $node || array_is_list($node)) {
            throw InvalidRule::malformed($path, 'a condition is an object');
        }

        $keys = array_keys($node);

        if (\array_key_exists('type', $node)) {
            if ([] !== array_diff($keys, ['type', 'operands'])) {
                throw InvalidRule::malformed($path, 'a leaf holds "type" and "operands" only');
            }

            $operands = \array_key_exists('operands', $node) ? $node['operands'] : [];

            if (!\is_string($node['type']) || !\is_array($operands)) {
                throw InvalidRule::malformed($path, 'a leaf has a text "type" and an object "operands"');
            }

            return new Leaf($node['type'], $operands);
        }

        if (1 !== \count($keys)) {
            throw InvalidRule::malformed($path, 'a condition holds one of "all", "any", "not" or "type"');
        }

        $key = $keys[0];
        $content = $node[$key];

        if ('not' === $key) {
            return new Not($this->node($content, $path.'/not', $depth + 1, $nodes));
        }

        if ('all' !== $key && 'any' !== $key) {
            throw InvalidRule::malformed($path, \sprintf('"%s" is not a condition; use "all", "any", "not" or "type"', InvalidRule::shown((string) $key)));
        }

        if (!\is_array($content) || [] === $content || !array_is_list($content)) {
            throw InvalidRule::malformed($path, \sprintf('"%s" holds a non-empty list of conditions', $key));
        }

        $children = [];

        foreach ($content as $index => $child) {
            $children[] = $this->node($child, \sprintf('%s/%s/%d', $path, $key, $index), $depth + 1, $nodes);
        }

        return 'all' === $key ? new All(...$children) : new Any(...$children);
    }
}

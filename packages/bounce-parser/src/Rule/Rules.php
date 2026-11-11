<?php

declare(strict_types=1);

namespace Phil\BounceParser\Rule;

use ArrayIterator;
use IteratorAggregate;
use Override;
use Phil\BounceParser\Exception\InvalidRule;
use Traversable;

/**
 * The rules of the application in the order they are tried: the first that matches decides. A rule goes at the end, or
 * before or after a rule it names; nothing is ordered by number.
 *
 * @implements IteratorAggregate<int, Rule>
 */
final readonly class Rules implements IteratorAggregate
{
    /** @var list<Rule> */
    private array $rules;

    /**
     * @throws InvalidRule when two rules have the same name
     */
    public function __construct(Rule ...$rules)
    {
        $names = [];

        foreach ($rules as $rule) {
            if (\in_array($rule->name, $names, true)) {
                throw InvalidRule::duplicate($rule->name);
            }

            $names[] = $rule->name;
        }

        $this->rules = array_values($rules);
    }

    /**
     * The rules with one more, at the end, or just before or just after the rule named; `$before` wins when both are
     * given.
     *
     * @throws InvalidRule when its name is taken, or no rule has the name it is placed by
     */
    public function with(Rule $rule, ?string $before = null, ?string $after = null): self
    {
        $anchor = $before ?? $after;

        if (null === $anchor) {
            return new self(...[...$this->rules, $rule]);
        }

        foreach ($this->rules as $index => $existing) {
            if ($existing->name === $anchor) {
                $position = null === $before ? $index + 1 : $index;

                return new self(...array_merge(\array_slice($this->rules, 0, $position), [$rule], \array_slice($this->rules, $position)));
            }
        }

        throw InvalidRule::unknown($anchor);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (Rule $rule): string => $rule->name, $this->rules);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rules);
    }
}

<?php

declare(strict_types=1);

namespace Phil\Rules\Type;

use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Type\BuiltIn\DateCompare;
use Phil\Rules\Type\BuiltIn\FactCompare;
use Phil\Rules\Type\BuiltIn\FactContains;
use Phil\Rules\Type\BuiltIn\FactEquals;
use Phil\Rules\Type\BuiltIn\FactIn;
use Phil\Rules\Type\BuiltIn\FactPresent;

/**
 * The registry of condition types a rule may name. A rule naming a type outside it is refused when it is loaded.
 * Immutable: `with()` returns a new registry.
 */
final readonly class ConditionTypes
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D';

    /** @var array<string, ConditionType> */
    private array $types;

    public function __construct(ConditionType ...$types)
    {
        $byName = [];

        foreach ($types as $type) {
            $name = $type->name();

            if (\strlen($name) > 64 || 1 !== preg_match(self::NAME_PATTERN, $name)) {
                throw InvalidConditionType::name($name);
            }

            if (isset($byName[$name])) {
                throw InvalidConditionType::duplicate($name);
            }

            $byName[$name] = $type;
        }

        $this->types = $byName;
    }

    /**
     * The generic comparisons on named facts: `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`,
     * `fact.present` and `date.compare`.
     */
    public static function builtIn(): self
    {
        return new self(new FactEquals(), new FactIn(), new FactContains(), new FactCompare(), new FactPresent(), new DateCompare());
    }

    public function with(ConditionType ...$types): self
    {
        return new self(...array_values($this->types), ...$types);
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): ?ConditionType
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->types);
    }

    /**
     * Every type with the schema of its operands, for a rule builder or an API description.
     *
     * @return array<string, list<array{name: string, kind: string, required: bool, choices?: list<string>, maxLength?: int, maxItems?: int, min?: int, max?: int}>>
     */
    public function describe(): array
    {
        return array_map(static fn (ConditionType $type): array => $type->operands()->describe(), $this->types);
    }
}

<?php

declare(strict_types=1);

namespace Phil\FormLogic\Expression;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;

/**
 * Presence: the names among these that have no value — not answered, `null` or `""` —, `{"missing": ["email"]}`. With
 * `$atLeast`, JsonLogic's `missing_some`, `{"missing_some": [1, ["email", "phone"]]}`: none once that many have one,
 * else the missing ones. The result is a list, false when empty.
 */
final readonly class Missing implements Expression
{
    /**
     * @param list<string> $names   at least one
     * @param int|null     $atLeast how many of the names must have a value, 0 to their count; null: all of them
     *
     * @throws InvalidLogic without a name, when a name is invalid, or when `$atLeast` is out of range
     */
    public function __construct(public array $names, public ?int $atLeast = null)
    {
        if ([] === $names) {
            throw InvalidLogic::because('A "missing" names at least one variable.');
        }

        foreach ($names as $name) {
            Variable::checkName($name);
        }

        if (null !== $atLeast && ($atLeast < 0 || $atLeast > \count($names))) {
            throw InvalidLogic::because(\sprintf('A "missing_some" needs 0 to %d of its names.', \count($names)));
        }
    }

    #[Override]
    public function jsonLogic(): array
    {
        return null === $this->atLeast ? ['missing' => $this->names] : ['missing_some' => [$this->atLeast, $this->names]];
    }

    #[Override]
    public function children(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Override;
use Phil\DoctrineBehaviours\Contract\PersonalFields;

/**
 * The personal fields listed by class: a class has those of every listed class or interface it is, each once, in the
 * order they are listed.
 */
final readonly class ListedPersonalFields implements PersonalFields
{
    /**
     * @param array<class-string, list<string>> $fields
     */
    public function __construct(
        private array $fields,
    ) {
    }

    #[Override]
    public function of(string $class): array
    {
        $fields = [];

        foreach ($this->fields as $listed => $names) {
            if (is_a($class, $listed, true)) {
                $fields = [...$fields, ...$names];
            }
        }

        return array_values(array_unique($fields));
    }
}

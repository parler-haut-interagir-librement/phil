<?php

declare(strict_types=1);

namespace Phil\FormLogic\Typing;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Variable;

/**
 * The names of the variables an expression reads, each once, in the order they first appear.
 */
final readonly class VariableNames
{
    /**
     * @return list<string>
     */
    public function of(Expression $expression): array
    {
        $names = [];
        $this->collect($expression, $names);

        return array_keys($names);
    }

    /**
     * @param array<string, true> $names
     */
    private function collect(Expression $expression, array &$names): void
    {
        if ($expression instanceof Variable) {
            $names[$expression->name] = true;
        }

        if ($expression instanceof Missing) {
            foreach ($expression->names as $name) {
                $names[$name] = true;
            }
        }

        foreach ($expression->children() as $child) {
            $this->collect($child, $names);
        }
    }
}

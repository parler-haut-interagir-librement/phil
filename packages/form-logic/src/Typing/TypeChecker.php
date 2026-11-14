<?php

declare(strict_types=1);

namespace Phil\FormLogic\Typing;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Arithmetic;
use Phil\FormLogic\Expression\Between;
use Phil\FormLogic\Expression\Comparison;
use Phil\FormLogic\Expression\In;
use Phil\FormLogic\Expression\ListOf;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Variable;

/**
 * Checks an expression against the types of the variables it reads, when the logic is defined rather than when it is
 * evaluated:
 *
 * - every variable it reads is in the schema;
 * - a variable compared with a constant is compared with a value of its type — a choice with one of its choices, a
 *   day with a valid `YYYY-MM-DD`, a boolean with `true` or `false`; `null` tells an unanswered variable for any type;
 * - only a number or a day is ordered, or placed in an interval; two variables compared are of the same type;
 * - `in` looks for a variable among constants of its type, for a choice in a `Choices` variable, or for a text in a
 *   `Text` variable;
 * - arithmetic computes with numbers only.
 */
final readonly class TypeChecker
{
    /**
     * @throws InvalidLogic naming the first rule the expression breaks
     */
    public function check(Expression $expression, Schema $schema): void
    {
        match (true) {
            $expression instanceof Variable => $this->definition($expression, $schema),
            $expression instanceof Missing => array_map(fn (string $name): VariableDefinition => $this->named($name, $schema), $expression->names),
            $expression instanceof Comparison => $this->comparison($expression, $schema),
            $expression instanceof Between => $this->between($expression, $schema),
            $expression instanceof In => $this->membership($expression, $schema),
            $expression instanceof Arithmetic => $this->arithmetic($expression, $schema),
            default => null,
        };

        foreach ($expression->children() as $child) {
            $this->check($child, $schema);
        }
    }

    private function definition(Variable $variable, Schema $schema): VariableDefinition
    {
        return $this->named($variable->name, $schema);
    }

    private function named(string $name, Schema $schema): VariableDefinition
    {
        return $schema->get($name) ?? throw InvalidLogic::because(\sprintf('The logic reads "%s", which is not a variable it may read.', $name));
    }

    private function comparison(Comparison $comparison, Schema $schema): void
    {
        $orders = $comparison->operator->orders();
        [$left, $right] = [$comparison->left, $comparison->right];

        if ($left instanceof Variable && $right instanceof Variable) {
            $a = $this->definition($left, $schema);
            $b = $this->definition($right, $schema);

            if ($a->type !== $b->type || ($orders && !$a->type->orders())) {
                throw InvalidLogic::because(\sprintf('"%s" and "%s" are compared, which are not of one type that %s.', $a->name, $b->name, $orders ? 'orders' : 'compares'));
            }
        } else {
            foreach ([[$left, $right], [$right, $left]] as [$variable, $constant]) {
                if ($variable instanceof Variable) {
                    $this->operand($this->definition($variable, $schema), $constant, $orders);
                }
            }
        }
    }

    private function between(Between $between, Schema $schema): void
    {
        if (!$between->value instanceof Variable) {
            return;
        }

        $definition = $this->definition($between->value, $schema);
        $this->operand($definition, $between->low, true);
        $this->operand($definition, $between->high, true);
    }

    private function membership(In $in, Schema $schema): void
    {
        $needle = $in->needle;
        $haystack = $in->haystack;

        if ($haystack instanceof Variable) {
            $definition = $this->definition($haystack, $schema);

            if (VariableType::Choices !== $definition->type && VariableType::Text !== $definition->type) {
                throw InvalidLogic::because(\sprintf('"%s" is looked into, which holds neither choices nor a text.', $definition->name));
            }

            if ($needle instanceof Literal) {
                $this->constant(new VariableDefinition('needle', VariableType::Choices === $definition->type ? VariableType::Choice : VariableType::Text, $definition->choices), $needle, false, $definition->name);
            }
        } elseif ($needle instanceof Variable && $haystack instanceof ListOf) {
            $definition = $this->definition($needle, $schema);

            foreach ($haystack->items as $item) {
                $this->operand($definition, $item, false);
            }
        }
    }

    private function arithmetic(Arithmetic $arithmetic, Schema $schema): void
    {
        foreach ($arithmetic->operands as $operand) {
            if ($operand instanceof Variable && VariableType::Number !== $this->definition($operand, $schema)->type) {
                throw InvalidLogic::because(\sprintf('"%s" is computed with, which is not a number.', $operand->name));
            }
        }
    }

    private function operand(VariableDefinition $definition, Expression $operand, bool $orders): void
    {
        if ($orders && !$definition->type->orders()) {
            throw InvalidLogic::because(\sprintf('"%s" is ordered, which is neither a number nor a day.', $definition->name));
        }

        if ($operand instanceof Literal) {
            $this->constant($definition, $operand, $orders, $definition->name);
        }
    }

    private function constant(VariableDefinition $definition, Literal $literal, bool $orders, string $name): void
    {
        $value = $literal->value;

        if (null === $value && !$orders) {
            return;
        }

        $fits = match ($definition->type) {
            VariableType::Text => \is_string($value),
            VariableType::Number => \is_int($value) || \is_float($value),
            VariableType::Boolean => \is_bool($value),
            VariableType::Date => \is_string($value) && $this->isDay($value),
            VariableType::Choice => \is_string($value) && \in_array($value, $definition->choices, true),
            VariableType::Choices => false,
        };

        if (!$fits) {
            throw InvalidLogic::because(\sprintf('"%s" is compared with %s, a value it never takes.', $name, mb_substr(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), 0, 80)));
        }
    }

    private function isDay(string $value): bool
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
            return false;
        }

        [, $year, $month, $day] = $parts;

        return checkdate((int) $month, (int) $day, (int) $year);
    }
}

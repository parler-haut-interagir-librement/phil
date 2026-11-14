<?php

declare(strict_types=1);

namespace Phil\FormLogic\Evaluation;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\AllOf;
use Phil\FormLogic\Expression\AnyOf;
use Phil\FormLogic\Expression\Arithmetic;
use Phil\FormLogic\Expression\ArithmeticOperator;
use Phil\FormLogic\Expression\Between;
use Phil\FormLogic\Expression\Comparison;
use Phil\FormLogic\Expression\ComparisonOperator;
use Phil\FormLogic\Expression\Conditional;
use Phil\FormLogic\Expression\In;
use Phil\FormLogic\Expression\ListOf;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Not;
use Phil\FormLogic\Expression\Variable;

/**
 * Evaluates an expression against the values of an answer, as JsonLogic does and as the Stimulus controller of the
 * package does: same rules, same results. Each node is evaluated once at most, so the time is proportional to the
 * size of the expression and of the values it reads.
 *
 * The data is a map of names to values: `null`, booleans, numbers, texts, lists, and maps a dotted name reads into;
 * anything else reads as `null`.
 *
 * @phpstan-import-type Value from Values
 */
final readonly class Evaluator
{
    /**
     * The value of the expression for this data.
     *
     * @param array<array-key, mixed> $data
     *
     * @return Value
     */
    public function evaluate(Expression $expression, array $data): bool|int|float|string|array|null
    {
        return match (true) {
            $expression instanceof Literal => $expression->value,
            $expression instanceof Variable => $this->variable($expression, $data),
            $expression instanceof ListOf => array_map(fn (Expression $item): bool|int|float|string|array|null => $this->evaluate($item, $data), $expression->items),
            $expression instanceof Comparison => $this->compare($expression->operator, $this->evaluate($expression->left, $data), $this->evaluate($expression->right, $data)),
            $expression instanceof Between => $this->between($expression, $data),
            $expression instanceof Not => $expression->double === Values::truthy($this->evaluate($expression->operand, $data)),
            $expression instanceof AllOf => $this->first($expression->operands, $data, false),
            $expression instanceof AnyOf => $this->first($expression->operands, $data, true),
            $expression instanceof In => Values::contains($this->evaluate($expression->needle, $data), $this->evaluate($expression->haystack, $data)),
            $expression instanceof Missing => $this->missing($expression, $data),
            $expression instanceof Conditional => $this->conditional($expression, $data),
            $expression instanceof Arithmetic => $this->arithmetic($expression, $data),
            default => throw InvalidLogic::because(\sprintf('The evaluator reads the expressions of the library only, not %s.', $expression::class)),
        };
    }

    /**
     * Whether the expression is true for this data, by JsonLogic's truth.
     *
     * @param array<array-key, mixed> $data
     */
    public function holds(Expression $expression, array $data): bool
    {
        return Values::truthy($this->evaluate($expression, $data));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return Value
     */
    private function variable(Variable $variable, array $data): bool|int|float|string|array|null
    {
        $value = $this->lookup($variable->path(), $data);

        return null === $value && $variable->default instanceof Expression ? $this->evaluate($variable->default, $data) : $value;
    }

    /**
     * @param non-empty-list<string>  $path
     * @param array<array-key, mixed> $data
     *
     * @return Value
     */
    private function lookup(array $path, array $data): bool|int|float|string|array|null
    {
        $current = $data;

        foreach ($path as $segment) {
            if (!\is_array($current)) {
                return null;
            }

            // PHP reads a key made of digits, with no sign nor leading zero, as the integer index of a list.
            if (!\array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return Values::fromData($current);
    }

    /**
     * @param Value $left
     * @param Value $right
     */
    private function compare(ComparisonOperator $operator, bool|int|float|string|array|null $left, bool|int|float|string|array|null $right): bool
    {
        return match ($operator) {
            ComparisonOperator::Equal => Values::looselyEqual($left, $right),
            ComparisonOperator::NotEqual => !Values::looselyEqual($left, $right),
            ComparisonOperator::StrictlyEqual => Values::strictlyEqual($left, $right),
            ComparisonOperator::StrictlyNotEqual => !Values::strictlyEqual($left, $right),
            ComparisonOperator::Less => Values::less($left, $right),
            ComparisonOperator::LessOrEqual => Values::less($left, $right, true),
            ComparisonOperator::Greater => Values::less($right, $left),
            ComparisonOperator::GreaterOrEqual => Values::less($right, $left, true),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function between(Between $between, array $data): bool
    {
        $value = $this->evaluate($between->value, $data);

        return Values::less($this->evaluate($between->low, $data), $value, $between->inclusive)
            && Values::less($value, $this->evaluate($between->high, $data), $between->inclusive);
    }

    /**
     * The first operand whose truth is `$truth`, else the last one.
     *
     * @param list<Expression>        $operands
     * @param array<array-key, mixed> $data
     *
     * @return Value
     */
    private function first(array $operands, array $data, bool $truth): bool|int|float|string|array|null
    {
        $value = null;

        foreach ($operands as $operand) {
            $value = $this->evaluate($operand, $data);

            if ($truth === Values::truthy($value)) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private function missing(Missing $missing, array $data): array
    {
        $absent = [];

        foreach ($missing->names as $name) {
            $value = $this->lookup(explode('.', $name), $data);

            if (null === $value || '' === $value) {
                $absent[] = $name;
            }
        }

        if (null !== $missing->atLeast && \count($missing->names) - \count($absent) >= $missing->atLeast) {
            return [];
        }

        return $absent;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return Value
     */
    private function conditional(Conditional $conditional, array $data): bool|int|float|string|array|null
    {
        foreach ($conditional->branches as [$condition, $value]) {
            if ($this->holds($condition, $data)) {
                return $this->evaluate($value, $data);
            }
        }

        return $conditional->otherwise instanceof Expression ? $this->evaluate($conditional->otherwise, $data) : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function arithmetic(Arithmetic $arithmetic, array $data): int|float|null
    {
        $numbers = array_map(fn (Expression $operand): float => Values::toNumber($this->evaluate($operand, $data)), $arithmetic->operands);
        $first = $numbers[0];
        $second = $numbers[1] ?? null;

        $result = match ($arithmetic->operator) {
            ArithmeticOperator::Add => array_sum($numbers),
            ArithmeticOperator::Multiply => array_product($numbers),
            ArithmeticOperator::Subtract => null === $second ? -$first : $first - $second,
            ArithmeticOperator::Divide => 0.0 === $second ? \NAN : $first / (float) $second,
            ArithmeticOperator::Modulo => 0.0 === $second ? \NAN : fmod($first, (float) $second),
            ArithmeticOperator::Min => $this->extreme($first, $numbers, false),
            ArithmeticOperator::Max => $this->extreme($first, $numbers, true),
        };

        return Values::number((float) $result);
    }

    /**
     * @param list<float> $numbers
     */
    private function extreme(float $extreme, array $numbers, bool $largest): float
    {
        foreach ($numbers as $number) {
            if (is_nan($number)) {
                return \NAN;
            }

            $extreme = $largest ? max($extreme, $number) : min($extreme, $number);
        }

        return $extreme;
    }
}

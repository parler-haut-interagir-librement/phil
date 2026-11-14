<?php

declare(strict_types=1);

namespace Phil\FormLogic\JsonLogic;

use JsonException;
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
use stdClass;

/**
 * Reads a JsonLogic rule into an expression, refusing anything outside the subset the library evaluates: an object is
 * one operation, of one of the operators below, with the operands it takes; any other operator, an object of several
 * keys, a variable named by a number or by an empty text, or a rule over the limits is refused, never skipped.
 *
 * The subset: `var`, `missing`, `missing_some`, `==`, `!=`, `===`, `!==`, `<`, `<=`, `>`, `>=`, `!`, `!!`, `and`,
 * `or`, `if`, `in`, `+`, `-`, `*`, `/`, `%`, `min` and `max`.
 *
 * A rule comes as JSON, or decoded — `json_decode()`'s objects, or PHP arrays whose operations are maps.
 */
final readonly class JsonLogicReader
{
    /** The operators the reader reads. */
    public const array OPERATORS = ['var', 'missing', 'missing_some', '==', '!=', '===', '!==', '<', '<=', '>', '>=', '!', '!!', 'and', 'or', 'if', 'in', '+', '-', '*', '/', '%', 'min', 'max'];

    public function __construct(private Limits $limits = new Limits())
    {
    }

    /**
     * @throws InvalidLogic when the JSON is not a rule of the subset within the limits
     */
    public function read(string $json): Expression
    {
        if (\strlen($json) > $this->limits->maxBytes) {
            throw InvalidLogic::because(\sprintf('A rule is at most %d bytes of JSON.', $this->limits->maxBytes));
        }

        // A rule `maxDepth` operations deep nests twice as many JSON objects and arrays — an operation is an object
        // holding a list —, one more for the list of a `var` at the deepest level, and json_decode() counts the scalars.
        try {
            $rule = json_decode($json, false, 2 * $this->limits->maxDepth + 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidLogic::because(\sprintf('A rule is valid JSON, nested at most %d operations deep: %s', $this->limits->maxDepth, $jsonException->getMessage()));
        }

        return $this->parse($rule);
    }

    /**
     * @param mixed $rule a decoded rule: a scalar, a list, a `stdClass` or a map holding one operation
     *
     * @throws InvalidLogic when the rule is not one of the subset within the limits
     */
    public function parse(mixed $rule): Expression
    {
        $nodes = 0;

        return $this->node($rule, 0, $nodes);
    }

    private function node(mixed $rule, int $depth, int &$nodes): Expression
    {
        if (++$nodes > $this->limits->maxNodes) {
            throw InvalidLogic::because(\sprintf('A rule has at most %d nodes.', $this->limits->maxNodes));
        }

        if ($depth > $this->limits->maxDepth) {
            throw InvalidLogic::because(\sprintf('A rule is nested at most %d operations deep.', $this->limits->maxDepth));
        }

        if ($rule instanceof stdClass) {
            return $this->operation(get_object_vars($rule), $depth, $nodes);
        }

        if (null === $rule || \is_scalar($rule)) {
            return $this->literal($rule);
        }

        if (!\is_array($rule)) {
            throw InvalidLogic::because(\sprintf('A rule holds JSON values only, not %s.', get_debug_type($rule)));
        }

        if (array_is_list($rule)) {
            return new ListOf($this->list($rule, $depth, $nodes));
        }

        return $this->operation($rule, $depth, $nodes);
    }

    private function literal(string|int|float|bool|null $value): Literal
    {
        if (\is_string($value) && (mb_strlen($value) > $this->limits->maxTextLength || !mb_check_encoding($value, 'UTF-8'))) {
            throw InvalidLogic::because(\sprintf('A text of a rule is UTF-8, at most %d characters.', $this->limits->maxTextLength));
        }

        return new Literal($value);
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<Expression>
     */
    private function list(array $items, int $depth, int &$nodes): array
    {
        if (\count($items) > $this->limits->maxListItems) {
            throw InvalidLogic::because(\sprintf('A list of a rule has at most %d items.', $this->limits->maxListItems));
        }

        $expressions = [];

        foreach ($items as $item) {
            $expressions[] = $this->node($item, $depth + 1, $nodes);
        }

        return $expressions;
    }

    /**
     * @param array<array-key, mixed> $rule
     */
    private function operation(array $rule, int $depth, int &$nodes): Expression
    {
        $operator = array_key_first($rule);

        if (1 !== \count($rule) || !\in_array($operator, self::OPERATORS, true)) {
            throw InvalidLogic::because(\sprintf('An object of a rule is one operation of the subset: %s; "%s" is not read.', implode(', ', self::OPERATORS), mb_substr((string) $operator, 0, 40)));
        }

        $arguments = $rule[$operator];

        if ('var' === $operator) {
            return $this->variable($arguments, $depth, $nodes);
        }

        if ('missing' === $operator || 'missing_some' === $operator) {
            return $this->missing($operator, $arguments);
        }

        $operands = $this->list(\is_array($arguments) && array_is_list($arguments) ? $arguments : [$arguments], $depth, $nodes);

        return match ($operator) {
            '==', '!=', '===', '!==', '>', '>=' => $this->comparison(ComparisonOperator::from($operator), $operands),
            '<', '<=' => 3 === \count($operands)
                ? new Between($operands[0], $operands[1], $operands[2], '<=' === $operator)
                : $this->comparison(ComparisonOperator::from($operator), $operands),
            '!', '!!' => new Not($this->only($operator, $operands), '!!' === $operator),
            'and' => new AllOf($operands),
            'or' => new AnyOf($operands),
            'if' => $this->conditional($operands),
            'in' => $this->membership($operands),
            default => $this->arithmetic(ArithmeticOperator::from($operator), $operands),
        };
    }

    private function variable(mixed $arguments, int $depth, int &$nodes): Variable
    {
        if (\is_array($arguments) && array_is_list($arguments) && \in_array(\count($arguments), [1, 2], true)) {
            $name = $arguments[0];
            $default = 2 === \count($arguments) ? $this->node($arguments[1], $depth + 1, $nodes) : null;
        } else {
            $name = $arguments;
            $default = null;
        }

        if (!\is_string($name)) {
            throw InvalidLogic::because('A "var" names its variable by a text, with a default after it or none.');
        }

        return new Variable($name, $default);
    }

    private function missing(string $operator, mixed $arguments): Missing
    {
        $atLeast = null;

        if ('missing_some' === $operator) {
            if (!\is_array($arguments) || !array_is_list($arguments) || 2 !== \count($arguments) || !\is_int($arguments[0])) {
                throw InvalidLogic::because('A "missing_some" takes how many names are needed, then the list of names.');
            }

            [$atLeast, $arguments] = $arguments;
        }

        $names = \is_array($arguments) ? $arguments : [$arguments];

        if ([] === $names || !array_is_list($names) || \count($names) > $this->limits->maxListItems) {
            throw InvalidLogic::because(\sprintf('A "%s" lists 1 to %d names.', $operator, $this->limits->maxListItems));
        }

        $texts = [];

        foreach ($names as $name) {
            if (!\is_string($name)) {
                throw InvalidLogic::because(\sprintf('A "%s" lists the names of variables, as texts.', $operator));
            }

            $texts[] = $name;
        }

        return new Missing($texts, $atLeast);
    }

    /**
     * @param list<Expression> $operands
     */
    private function comparison(ComparisonOperator $operator, array $operands): Comparison
    {
        if (2 !== \count($operands)) {
            throw InvalidLogic::because(\sprintf('A "%s" compares 2 operands%s.', $operator->value, \in_array($operator, [ComparisonOperator::Less, ComparisonOperator::LessOrEqual], true) ? ', or places one between 2 bounds' : ''));
        }

        return new Comparison($operator, $operands[0], $operands[1]);
    }

    /**
     * @param list<Expression> $operands
     */
    private function only(string $operator, array $operands): Expression
    {
        if (1 !== \count($operands)) {
            throw InvalidLogic::because(\sprintf('A "%s" takes 1 operand.', $operator));
        }

        return $operands[0];
    }

    /**
     * @param list<Expression> $operands
     */
    private function conditional(array $operands): Conditional
    {
        $branches = [];
        $count = \count($operands);

        for ($index = 0; $index + 1 < $count; $index += 2) {
            $branches[] = [$operands[$index], $operands[$index + 1]];
        }

        return new Conditional($branches, 1 === $count % 2 ? $operands[$count - 1] : null);
    }

    /**
     * @param list<Expression> $operands
     */
    private function membership(array $operands): In
    {
        if (2 !== \count($operands)) {
            throw InvalidLogic::because('An "in" takes a needle and what it is looked for in.');
        }

        return new In($operands[0], $operands[1]);
    }

    /**
     * @param list<Expression> $operands
     */
    private function arithmetic(ArithmeticOperator $operator, array $operands): Arithmetic
    {
        if ([] === $operands) {
            throw InvalidLogic::because(\sprintf('A "%s" takes at least one operand.', $operator->value));
        }

        return new Arithmetic($operator, $operands);
    }
}

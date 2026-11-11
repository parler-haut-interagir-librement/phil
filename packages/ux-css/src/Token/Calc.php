<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

use ArrayObject;
use Closure;

/**
 * The value of a length, a duration or a number as phil-ux writes them: `1rem`, `150ms`, `1.25`, and `calc()` of those
 * with `+`, `-`, `*`, `/`, parentheses and `var()`, followed through the custom properties a lookup gives.
 *
 * It computes what a stylesheet could at its defaults, nothing more: a sum of two units, a product of two lengths or a
 * division by zero has no value, and gives `null`, as does anything else it does not read. The text, the number of
 * pieces and the depth of `var()` are bounded, and a property computed once is not computed again, so any input is
 * read in bounded time.
 */
final class Calc
{
    /**
     * The longest text read.
     */
    public const int MAX_LENGTH = 1024;

    /**
     * The most `var()` followed in a row, beyond which a value is taken for a cycle.
     */
    public const int MAX_DEPTH = 16;

    /**
     * The most pieces — numbers, operators, parentheses — in one text.
     */
    private const int MAX_PIECES = 256;

    private const string PIECE = '/\G\s*(?:(calc\()|var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,[^()]*)?\)|([+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)([a-z]+|%)?|[-+*\/()])/iA';

    /**
     * Values, and operators and parentheses as written.
     *
     * @var list<string|Quantity>
     */
    private array $pieces = [];

    private int $position = 0;

    /**
     * @param Closure(string): ?string      $lookup the value of a custom property, or `null` when it is not declared
     * @param ArrayObject<string, Quantity> $known  the properties computed so far
     */
    private function __construct(
        private readonly Closure $lookup,
        private readonly int $depth,
        private readonly ArrayObject $known,
    ) {
    }

    /**
     * @param Closure(string): ?string $lookup the value of a custom property, or `null` when it is not declared
     */
    public static function evaluate(string $expression, Closure $lookup): ?Quantity
    {
        /** @var ArrayObject<string, Quantity> $known */
        $known = new ArrayObject();

        return new self($lookup, 0, $known)->read($expression);
    }

    private function read(string $expression): ?Quantity
    {
        if ($this->depth > self::MAX_DEPTH || \strlen($expression) > self::MAX_LENGTH || !$this->split($expression)) {
            return null;
        }

        $value = $this->sum();

        return $this->position === \count($this->pieces) ? $value : null;
    }

    /**
     * Cuts the text into numbers, `var()` already followed, operators and parentheses; false on anything else.
     */
    private function split(string $expression): bool
    {
        $offset = 0;

        while ('' !== trim(substr($expression, $offset))) {
            if (1 !== preg_match(self::PIECE, $expression, $match, \PREG_UNMATCHED_AS_NULL, $offset) || \count($this->pieces) >= self::MAX_PIECES) {
                return false;
            }

            $offset += \strlen($match[0]);

            if (null !== $match[1]) {
                $this->pieces[] = '(';
            } elseif (null !== $match[2]) {
                $quantity = $this->property($match[2]);

                if (!$quantity instanceof Quantity) {
                    return false;
                }

                $this->pieces[] = $quantity;
            } elseif (null !== $match[3]) {
                $this->pieces[] = new Quantity((float) $match[3], strtolower($match[4] ?? ''));
            } else {
                $this->pieces[] = trim($match[0]);
            }
        }

        return [] !== $this->pieces;
    }

    /**
     * The value of a custom property, computed once: a property that cannot be computed fails what names it at once,
     * so only what succeeds needs keeping.
     */
    private function property(string $name): ?Quantity
    {
        if (isset($this->known[$name])) {
            return $this->known[$name];
        }

        $value = ($this->lookup)($name);
        $quantity = null === $value ? null : new self($this->lookup, $this->depth + 1, $this->known)->read($value);

        if ($quantity instanceof Quantity) {
            $this->known[$name] = $quantity;
        }

        return $quantity;
    }

    private function sum(): ?Quantity
    {
        $left = $this->product();

        while ($left instanceof Quantity && \in_array($this->peek(), ['+', '-'], true)) {
            $operator = $this->next();
            $right = $this->product();

            if (!$right instanceof Quantity || $left->unit !== $right->unit) {
                return null;
            }

            $left = new Quantity('+' === $operator ? $left->value + $right->value : $left->value - $right->value, $left->unit);
        }

        return $left;
    }

    private function product(): ?Quantity
    {
        $left = $this->factor();

        while ($left instanceof Quantity && \in_array($this->peek(), ['*', '/'], true)) {
            $operator = $this->next();
            $right = $this->factor();

            if (!$right instanceof Quantity) {
                return null;
            }

            $left = '*' === $operator ? $this->multiply($left, $right) : $this->divide($left, $right);
        }

        return $left;
    }

    private function factor(): ?Quantity
    {
        $piece = $this->pieces[$this->position] ?? null;

        if (null === $piece) {
            return null;
        }

        ++$this->position;

        if ($piece instanceof Quantity) {
            return $piece;
        }

        if ('-' === $piece) {
            $value = $this->factor();

            return $value instanceof Quantity ? new Quantity(-$value->value, $value->unit) : null;
        }

        if ('(' !== $piece) {
            return null;
        }

        $value = $this->sum();

        return $value instanceof Quantity && ')' === $this->next() ? $value : null;
    }

    private function multiply(Quantity $left, Quantity $right): ?Quantity
    {
        if (!$left->isNumber() && !$right->isNumber()) {
            return null;
        }

        return new Quantity($left->value * $right->value, $left->isNumber() ? $right->unit : $left->unit);
    }

    private function divide(Quantity $left, Quantity $right): ?Quantity
    {
        if (0.0 === $right->value) {
            return null;
        }

        if ($right->isNumber()) {
            return new Quantity($left->value / $right->value, $left->unit);
        }

        return $left->unit === $right->unit ? new Quantity($left->value / $right->value) : null;
    }

    private function peek(): ?string
    {
        $piece = $this->pieces[$this->position] ?? null;

        return \is_string($piece) ? $piece : null;
    }

    private function next(): ?string
    {
        $piece = $this->peek();
        ++$this->position;

        return $piece;
    }
}

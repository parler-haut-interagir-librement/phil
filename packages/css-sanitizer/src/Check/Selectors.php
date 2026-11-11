<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Check;

use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;

/**
 * Whether a complex selector is allowed: the grammar of Selectors Level 4 for type, universal, class, ID and
 * attribute selectors, pseudo-classes and pseudo-elements, joined by the descendant, child and sibling combinators —
 * with the policy's pseudo-classes, pseudo-elements and attributes only. Namespaces and column combinators are
 * refused.
 *
 * A nested selector, the selector of a rule inside a style rule, may also start with a combinator and hold the nesting
 * selector `&` in any of its compounds, but never inside the arguments of `:not()`, `:is()` or `:where()`: there,
 * `:not(&)` would match every element the parent rule does not, outside the policy's scope.
 *
 * @internal
 */
final readonly class Selectors
{
    /** The functional pseudo-classes whose arguments the check knows how to read. */
    private const array FUNCTIONAL = [
        'not', 'is', 'where', 'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type', 'lang', 'dir',
    ];

    /** The pseudo-elements CSS 2 wrote with one colon. */
    private const array LEGACY_PSEUDO_ELEMENTS = ['before', 'after', 'first-line', 'first-letter'];

    public function __construct(
        private Policy $policy,
    ) {
    }

    /**
     * @param list<ComponentValue> $selector a complex selector, without white space at its ends
     */
    public function allows(array $selector, bool $pseudoElements = true): bool
    {
        return $this->complex($selector, $pseudoElements, false);
    }

    /**
     * The selector of a rule nested in a style rule: relative to its parent, it may start with a combinator, and its
     * compounds may hold `&`.
     *
     * @param list<ComponentValue> $selector a relative selector, without white space at its ends
     */
    public function allowsNested(array $selector): bool
    {
        if ($this->isCombinator($selector[0] ?? null)) {
            $selector = Parser::trim(\array_slice($selector, 1));
        }

        return $this->complex($selector, true, true);
    }

    /**
     * @param list<ComponentValue> $selector
     */
    private function complex(array $selector, bool $pseudoElements, bool $nesting): bool
    {
        $index = 0;
        $count = \count($selector);
        $hasPseudoElement = false;

        if (!$this->compound($selector, $index, $pseudoElements, $nesting, $hasPseudoElement)) {
            return false;
        }

        while ($index < $count) {
            if ($hasPseudoElement) {
                return false;
            }

            $this->skipWhitespace($selector, $index);

            if ($this->isCombinator($selector[$index] ?? null)) {
                ++$index;
                $this->skipWhitespace($selector, $index);
            }

            if (!$this->compound($selector, $index, $pseudoElements, $nesting, $hasPseudoElement)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A compound selector from the index on; false when it is empty or holds anything not allowed.
     *
     * @param list<ComponentValue> $selector
     */
    private function compound(array $selector, int &$index, bool $pseudoElements, bool $nesting, bool &$hasPseudoElement): bool
    {
        $start = $index;
        $first = $selector[$index] ?? null;

        if ($first instanceof Token && (TokenType::Ident === $first->type || $first->is(TokenType::Delim, '*'))) {
            ++$index;
        }

        while (null !== ($value = $selector[$index] ?? null) && !self::isWhitespace($value) && !$this->isCombinator($value)) {
            $element = $this->pseudoElement($selector, $index, $pseudoElements);

            if (true === $element) {
                $hasPseudoElement = true;

                continue;
            }

            if (false === $element || $hasPseudoElement || !$this->subclass($selector, $index, $nesting)) {
                return false;
            }
        }

        return $index > $start;
    }

    /**
     * A pseudo-element at the index, consumed: true when it is one and allowed, false when it is one and refused, null
     * when there is none.
     *
     * @param list<ComponentValue> $selector
     */
    private function pseudoElement(array $selector, int &$index, bool $allowed): ?bool
    {
        if (!$this->isColon($selector[$index] ?? null)) {
            return null;
        }

        $double = $this->isColon($selector[$index + 1] ?? null);
        $name = $selector[$index + ($double ? 2 : 1)] ?? null;

        if (!$name instanceof Token || TokenType::Ident !== $name->type) {
            return $double ? false : null;
        }

        $lower = strtolower($name->value);

        if (!$double && !\in_array($lower, self::LEGACY_PSEUDO_ELEMENTS, true)) {
            return null;
        }

        $index += $double ? 3 : 2;

        return $allowed && isset($this->policy->pseudoElements[$lower]);
    }

    /**
     * An ID, class, attribute selector, pseudo-class or, in a nested selector, `&` at the index, consumed when it is
     * allowed.
     *
     * @param list<ComponentValue> $selector
     */
    private function subclass(array $selector, int &$index, bool $nesting): bool
    {
        $value = $selector[$index];

        if ($value instanceof Token && $value->is(TokenType::Delim, '&')) {
            ++$index;

            return $nesting;
        }

        if ($value instanceof Token && TokenType::Hash === $value->type) {
            ++$index;

            return $value->isId;
        }

        if ($value instanceof SimpleBlock) {
            ++$index;

            return TokenType::OpenSquare === $value->opening && $this->attribute(Parser::trim($value->value));
        }

        $next = $selector[$index + 1] ?? null;
        $index += 2;

        if ($value instanceof Token && $value->is(TokenType::Delim, '.')) {
            return $next instanceof Token && TokenType::Ident === $next->type;
        }

        if (!$this->isColon($value)) {
            return false;
        }

        if ($next instanceof Token && TokenType::Ident === $next->type) {
            $name = strtolower($next->value);

            return isset($this->policy->pseudoClasses[$name]) && !\in_array($name, self::FUNCTIONAL, true);
        }

        return $next instanceof FunctionValue && $this->functionalPseudoClass($next);
    }

    private function functionalPseudoClass(FunctionValue $function): bool
    {
        $name = $function->lowerName();

        if (!isset($this->policy->pseudoClasses[$name])) {
            return false;
        }

        $arguments = Parser::trim($function->arguments);

        return match ($name) {
            'not', 'is', 'where' => array_all(Parser::splitAtCommas($arguments), fn (array $selector): bool => $this->allows($selector, false)),
            'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type' => $this->only($arguments, [TokenType::Ident, TokenType::Number, TokenType::Dimension, TokenType::Whitespace], ['+', '-']),
            'lang' => $this->only($arguments, [TokenType::Ident, TokenType::String, TokenType::Comma, TokenType::Whitespace], []),
            'dir' => 1 === \count($arguments) && $arguments[0] instanceof Token && ($arguments[0]->isIdent('ltr') || $arguments[0]->isIdent('rtl')),
            default => false,
        };
    }

    /**
     * The content of an attribute selector: a name the policy allows, then, optionally, a matcher, a value and a case
     * flag.
     *
     * @param list<ComponentValue> $content
     */
    private function attribute(array $content): bool
    {
        $parts = array_values(array_filter($content, static fn (ComponentValue $value): bool => !self::isWhitespace($value)));
        $name = $parts[0] ?? null;

        if (!$name instanceof Token || TokenType::Ident !== $name->type || !$this->policy->allowsAttribute($name->value)) {
            return false;
        }

        if (1 === \count($parts)) {
            return true;
        }

        $at = 1;
        $matcher = $parts[$at];

        if ($matcher instanceof Token && TokenType::Delim === $matcher->type && \in_array($matcher->value, ['~', '|', '^', '$', '*'], true)) {
            ++$at;
            $matcher = $parts[$at] ?? null;
        }

        $value = $parts[$at + 1] ?? null;
        $flag = $parts[$at + 2] ?? null;

        return $matcher instanceof Token && $matcher->is(TokenType::Delim, '=')
            && $value instanceof Token && (TokenType::Ident === $value->type || TokenType::String === $value->type)
            && (null === $flag || ($flag instanceof Token && ($flag->isIdent('i') || $flag->isIdent('s'))))
            && \count($parts) <= $at + 3;
    }

    /**
     * Whether the values are non-empty and only tokens of these types, delims among these characters.
     *
     * @param list<ComponentValue> $values
     * @param list<TokenType>      $types
     * @param list<string>         $delims
     */
    private function only(array $values, array $types, array $delims): bool
    {
        return [] !== $values && array_all($values, static fn (ComponentValue $value): bool => $value instanceof Token
            && (\in_array($value->type, $types, true) || (TokenType::Delim === $value->type && \in_array($value->value, $delims, true))));
    }

    /**
     * @param list<ComponentValue> $selector
     */
    private function skipWhitespace(array $selector, int &$index): void
    {
        while (self::isWhitespace($selector[$index] ?? null)) {
            ++$index;
        }
    }

    private function isCombinator(?ComponentValue $value): bool
    {
        return $value instanceof Token && TokenType::Delim === $value->type && \in_array($value->value, ['>', '+', '~'], true);
    }

    private function isColon(?ComponentValue $value): bool
    {
        return $value instanceof Token && TokenType::Colon === $value->type;
    }

    private static function isWhitespace(?ComponentValue $value): bool
    {
        return $value instanceof Token && TokenType::Whitespace === $value->type;
    }
}

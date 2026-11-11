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
 * Whether the prelude of an at-rule is allowed: the media queries of `@media`, the condition of `@supports`, the page
 * selectors of `@page`, the conditions of `@import`. None of them may load anything.
 *
 * @internal
 */
final readonly class Preludes
{
    /** The page pseudo-classes of CSS Paged Media. */
    private const array PAGE_PSEUDO_CLASSES = ['first', 'left', 'right', 'blank'];

    private const array MEDIA_KEYWORDS = ['only', 'not', 'and', 'or'];

    private const array SUPPORTS_KEYWORDS = ['not', 'and', 'or'];

    public function __construct(
        private Policy $policy,
    ) {
    }

    /**
     * Media queries: keywords, the policy's media types, and features in parentheses made of names, numbers,
     * dimensions, ratios and comparisons. An empty prelude matches all media.
     *
     * @param list<ComponentValue> $prelude
     */
    public function media(array $prelude): bool
    {
        if ([] === $prelude) {
            return true;
        }

        return array_all(Parser::splitAtCommas($prelude), fn (array $query): bool => [] !== $query && array_all(
            $query,
            fn (ComponentValue $value): bool => self::isWhitespace($value)
                || ($value instanceof Token && TokenType::Ident === $value->type && (\in_array(strtolower($value->value), self::MEDIA_KEYWORDS, true) || isset($this->policy->mediaTypes[strtolower($value->value)])))
                || self::isMediaFeature($value),
        ));
    }

    /**
     * A `@supports` condition: keywords, conditions in parentheses and `selector()`, none holding a URL.
     *
     * @param list<ComponentValue> $prelude
     */
    public function supports(array $prelude): bool
    {
        return [] !== $prelude && array_all($prelude, static fn (ComponentValue $value): bool => self::isWhitespace($value)
            || ($value instanceof Token && TokenType::Ident === $value->type && \in_array(strtolower($value->value), self::SUPPORTS_KEYWORDS, true))
            || ($value instanceof SimpleBlock && TokenType::OpenParenthesis === $value->opening && self::isCondition($value->value))
            || ($value instanceof FunctionValue && 'selector' === $value->lowerName() && self::isCondition($value->arguments)));
    }

    /**
     * Page selectors: each an optional page name followed by page pseudo-classes, none of them apart. An empty prelude
     * selects every page.
     *
     * @param list<ComponentValue> $prelude
     */
    public function page(array $prelude): bool
    {
        if ([] === $prelude) {
            return true;
        }

        foreach (Parser::splitAtCommas($prelude) as $selector) {
            if ([] === $selector) {
                return false;
            }

            $count = \count($selector);
            $index = $selector[0] instanceof Token && TokenType::Ident === $selector[0]->type ? 1 : 0;

            for (; $index < $count; $index += 2) {
                $colon = $selector[$index];
                $name = $selector[$index + 1] ?? null;

                if (!($colon instanceof Token && TokenType::Colon === $colon->type && $name instanceof Token && TokenType::Ident === $name->type && \in_array(strtolower($name->value), self::PAGE_PSEUDO_CLASSES, true))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The conditions of an `@import` rule after its URL: an optional `supports()` condition, then media queries. A
     * layer is not among them.
     *
     * @param list<ComponentValue> $conditions
     */
    public function import(array $conditions): bool
    {
        $first = $conditions[0] ?? null;

        if ($first instanceof FunctionValue && 'supports' === $first->lowerName()) {
            if ([] === Parser::trim($first->arguments) || !self::isCondition($first->arguments)) {
                return false;
            }

            $conditions = Parser::trim(\array_slice($conditions, 1));
        }

        return $this->media($conditions);
    }

    private static function isMediaFeature(ComponentValue $value): bool
    {
        return $value instanceof SimpleBlock && TokenType::OpenParenthesis === $value->opening && array_all(
            $value->value,
            static fn (ComponentValue $part): bool => self::isMediaFeature($part) || ($part instanceof Token && (
                \in_array($part->type, [TokenType::Ident, TokenType::Number, TokenType::Dimension, TokenType::Percentage, TokenType::Colon, TokenType::Whitespace], true)
                || (TokenType::Delim === $part->type && \in_array($part->value, ['/', '<', '>', '='], true))
            )),
        );
    }

    /**
     * The content of a condition: any token but a URL, a bad token or a semicolon, in parentheses, brackets and
     * functions other than `url()`.
     *
     * @param list<ComponentValue> $values
     */
    private static function isCondition(array $values): bool
    {
        return array_all($values, static function (ComponentValue $value): bool {
            if ($value instanceof Token) {
                return !\in_array($value->type, [TokenType::Url, TokenType::BadUrl, TokenType::BadString, TokenType::Semicolon], true);
            }

            if ($value instanceof FunctionValue) {
                return 'url' !== $value->lowerName() && self::isCondition($value->arguments);
            }

            return TokenType::OpenCurly !== $value->opening && self::isCondition($value->value);
        });
    }

    private static function isWhitespace(ComponentValue $value): bool
    {
        return $value instanceof Token && TokenType::Whitespace === $value->type;
    }
}

<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Syntax;

use Phil\CssSanitizer\Exception\NestingTooDeep;

/**
 * The parser of CSS Syntax Level 3, section 5, written from the specification.
 *
 * The source is first read into component values — tokens, simple blocks and functions —, then lists of rules or of
 * declarations are read from them, as the specification's algorithms allow. A rule's block keeps its component values:
 * whether they hold rules or declarations depends on the rule, which the caller knows. What the specification drops as
 * a parse error is kept as `Malformed`. Blocks and functions nested deeper than the parser's limit throw
 * `NestingTooDeep`, so that a hostile source cannot exhaust the stack.
 */
final readonly class Parser
{
    public function __construct(
        private int $maxDepth = 64,
        private Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /** "Parse a stylesheet": the rules of a style sheet, `<!--` and `-->` skipped at its top level. */
    public function parseStylesheet(string $css): Stylesheet
    {
        return new Stylesheet($this->rules($this->parseComponentValues($css), true));
    }

    /**
     * "Parse a list of declarations": the content of a `style` attribute, or of a block that holds declarations.
     *
     * @return list<Declaration|AtRule|Malformed>
     */
    public function parseDeclarationList(string $css): array
    {
        return $this->declarations($this->parseComponentValues($css));
    }

    /**
     * "Parse a list of component values".
     *
     * @return list<ComponentValue>
     */
    public function parseComponentValues(string $css): array
    {
        $tokens = $this->tokenizer->tokenize($css);
        $index = 0;
        $values = [];

        while (TokenType::Eof !== $tokens[$index]->type) {
            $values[] = $this->consumeComponentValue($tokens, $index, 0);
        }

        return $values;
    }

    /**
     * "Consume a list of rules", from component values.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<QualifiedRule|AtRule|Malformed>
     */
    public function rules(array $values, bool $topLevel = false): array
    {
        $rules = [];
        $index = 0;
        $count = \count($values);

        while ($index < $count) {
            $value = $values[$index];

            if ($value instanceof Token && ($value->is(TokenType::Whitespace) || ($topLevel && ($value->is(TokenType::Cdo) || $value->is(TokenType::Cdc))))) {
                ++$index;
            } elseif ($value instanceof Token && $value->is(TokenType::AtKeyword)) {
                $rules[] = $this->consumeAtRule($value, $values, $index);
            } else {
                $rules[] = $this->consumeQualifiedRule($values, $index);
            }
        }

        return $rules;
    }

    /**
     * "Consume a list of declarations", from component values: declarations, and at-rules among them.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<Declaration|AtRule|Malformed>
     */
    public function declarations(array $values): array
    {
        $items = [];
        $index = 0;
        $count = \count($values);

        while ($index < $count) {
            $value = $values[$index];

            if ($value instanceof Token && ($value->is(TokenType::Whitespace) || $value->is(TokenType::Semicolon))) {
                ++$index;

                continue;
            }

            if ($value instanceof Token && $value->is(TokenType::AtKeyword)) {
                $items[] = $this->consumeAtRule($value, $values, $index);

                continue;
            }

            $content = [];

            while ($index < $count && !$this->isSemicolon($values[$index])) {
                $content[] = $values[$index];
                ++$index;
            }

            $items[] = $value instanceof Token && $value->is(TokenType::Ident)
                ? $this->consumeDeclaration($value, $content)
                : new Malformed($content, $value->line());
        }

        return $items;
    }

    /**
     * "Consume a block's contents", from component values, as CSS Nesting reads the block of a style rule:
     * declarations, at-rules, and nested style rules. An item that starts with a name is read as a declaration first;
     * when it is not one — no colon after the name, or a `{}` block among other values of a property that is not
     * custom — it is read again as a nested rule, whose prelude ends at its block or, as a parse error, at a
     * semicolon.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<Declaration|AtRule|QualifiedRule|Malformed>
     */
    public function blockContents(array $values): array
    {
        $items = [];
        $index = 0;
        $count = \count($values);

        while ($index < $count) {
            $value = $values[$index];

            if ($value instanceof Token && ($value->is(TokenType::Whitespace) || $value->is(TokenType::Semicolon))) {
                ++$index;

                continue;
            }

            if ($value instanceof Token && $value->is(TokenType::AtKeyword)) {
                $items[] = $this->consumeAtRule($value, $values, $index);

                continue;
            }

            $end = $value instanceof Token && $value->is(TokenType::Ident) ? $this->declarationEnd($value, $values, $index) : null;

            if ($value instanceof Token && null !== $end) {
                $items[] = $this->consumeDeclaration($value, \array_slice($values, $index, $end - $index));
                $index = $end;

                continue;
            }

            $items[] = $this->consumeNestedRule($values, $index);
        }

        return $items;
    }

    /**
     * Where the declaration that starts at the index ends, at its semicolon or at the end of the values; null when the
     * item cannot be a declaration and is a nested rule: no colon after its name, or, unless the property is custom, a
     * `{}` block beside other values. The reading stops as soon as it knows, so that a block of many nested rules is
     * read in linear time.
     *
     * @param list<ComponentValue> $values
     */
    private function declarationEnd(Token $name, array $values, int $index): ?int
    {
        $count = \count($values);
        $end = $index + 1;

        while ($end < $count && self::isWhitespace($values[$end])) {
            ++$end;
        }

        $colon = $values[$end] ?? null;

        if (!$colon instanceof Token || !$colon->is(TokenType::Colon)) {
            return null;
        }

        $custom = str_starts_with($name->value, '--');
        $block = false;
        $other = false;

        for (++$end; $end < $count && !$this->isSemicolon($values[$end]); ++$end) {
            $value = $values[$end];

            if ($custom || self::isWhitespace($value)) {
                continue;
            }

            if ($block || ($other && $this->isCurlyBlock($value))) {
                return null;
            }

            $block = $this->isCurlyBlock($value);
            $other = !$block;
        }

        return $end;
    }

    /**
     * A nested rule: its prelude up to its `{}` block; a semicolon first ends it as a parse error.
     *
     * @param list<ComponentValue> $values
     */
    private function consumeNestedRule(array $values, int &$index): QualifiedRule|Malformed
    {
        $line = $values[$index]->line();
        $prelude = [];
        $count = \count($values);

        while ($index < $count) {
            $value = $values[$index];
            ++$index;

            if ($this->isCurlyBlock($value)) {
                return new QualifiedRule($prelude, $value, $line);
            }

            if ($this->isSemicolon($value)) {
                break;
            }

            $prelude[] = $value;
        }

        return new Malformed($prelude, $line);
    }

    /**
     * @param list<ComponentValue> $values
     */
    private function consumeAtRule(Token $keyword, array $values, int &$index): AtRule
    {
        $prelude = [];
        $count = \count($values);
        ++$index;

        while ($index < $count) {
            $value = $values[$index];
            ++$index;

            if ($this->isSemicolon($value)) {
                break;
            }

            if ($this->isCurlyBlock($value)) {
                return new AtRule($keyword->value, $prelude, $value, $keyword->sourceLine);
            }

            $prelude[] = $value;
        }

        return new AtRule($keyword->value, $prelude, null, $keyword->sourceLine);
    }

    /**
     * @param list<ComponentValue> $values
     */
    private function consumeQualifiedRule(array $values, int &$index): QualifiedRule|Malformed
    {
        $line = $values[$index]->line();
        $prelude = [];
        $count = \count($values);

        while ($index < $count) {
            $value = $values[$index];
            ++$index;

            if ($this->isCurlyBlock($value)) {
                return new QualifiedRule($prelude, $value, $line);
            }

            $prelude[] = $value;
        }

        return new Malformed($prelude, $line);
    }

    /**
     * "Consume a declaration", from the component values up to its semicolon.
     *
     * @param list<ComponentValue> $content
     */
    private function consumeDeclaration(Token $name, array $content): Declaration|Malformed
    {
        $rest = self::trim(\array_slice($content, 1));

        if ([] === $rest || (!$rest[0] instanceof Token || !$rest[0]->is(TokenType::Colon))) {
            return new Malformed($content, $name->sourceLine);
        }

        $value = self::trim(\array_slice($rest, 1));
        $important = false;
        $last = \count($value) - 1;
        $tail = $value[$last] ?? null;

        if ($tail instanceof Token && $tail->isIdent('important')) {
            $bang = self::trim(\array_slice($value, 0, $last));
            $before = \count($bang) - 1;
            $mark = $bang[$before] ?? null;

            if ($mark instanceof Token && $mark->is(TokenType::Delim, '!')) {
                $important = true;
                $value = self::trim(\array_slice($bang, 0, $before));
            }
        }

        return new Declaration($name->value, $value, $important, $name->sourceLine);
    }

    /**
     * "Consume a component value", from tokens.
     *
     * @param list<Token> $tokens
     */
    private function consumeComponentValue(array $tokens, int &$index, int $depth): ComponentValue
    {
        $token = $tokens[$index];
        ++$index;

        $closing = match ($token->type) {
            TokenType::OpenCurly => TokenType::CloseCurly,
            TokenType::OpenSquare => TokenType::CloseSquare,
            TokenType::OpenParenthesis, TokenType::Function => TokenType::CloseParenthesis,
            default => null,
        };

        if (null === $closing) {
            return $token;
        }

        if ($depth >= $this->maxDepth) {
            throw NestingTooDeep::beyond($this->maxDepth, $token->sourceLine);
        }

        $content = [];

        while ($closing !== $tokens[$index]->type && TokenType::Eof !== $tokens[$index]->type) {
            $content[] = $this->consumeComponentValue($tokens, $index, $depth + 1);
        }

        if ($closing === $tokens[$index]->type) {
            ++$index;
        }

        if (TokenType::Function === $token->type) {
            return new FunctionValue($token->value, $content, $token->sourceLine);
        }

        return new SimpleBlock($token->type, $content, $token->sourceLine);
    }

    /**
     * The values without the white space at their start and end.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<ComponentValue>
     */
    public static function trim(array $values): array
    {
        $start = 0;
        $end = \count($values);

        while ($start < $end && self::isWhitespace($values[$start])) {
            ++$start;
        }

        while ($end > $start && self::isWhitespace($values[$end - 1])) {
            --$end;
        }

        return \array_slice($values, $start, $end - $start);
    }

    /**
     * The values cut at each comma of their top level, each part trimmed of white space.
     *
     * @param list<ComponentValue> $values
     *
     * @return non-empty-list<list<ComponentValue>>
     */
    public static function splitAtCommas(array $values): array
    {
        $parts = [];
        $part = [];

        foreach ($values as $value) {
            if ($value instanceof Token && $value->is(TokenType::Comma)) {
                $parts[] = self::trim($part);
                $part = [];
            } else {
                $part[] = $value;
            }
        }

        $parts[] = self::trim($part);

        return $parts;
    }

    private static function isWhitespace(ComponentValue $value): bool
    {
        return $value instanceof Token && $value->is(TokenType::Whitespace);
    }

    private function isSemicolon(ComponentValue $value): bool
    {
        return $value instanceof Token && $value->is(TokenType::Semicolon);
    }

    /**
     * @phpstan-assert-if-true SimpleBlock $value
     */
    private function isCurlyBlock(ComponentValue $value): bool
    {
        return $value instanceof SimpleBlock && TokenType::OpenCurly === $value->opening;
    }
}

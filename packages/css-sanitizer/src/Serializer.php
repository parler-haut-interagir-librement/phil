<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\ImportRule;
use Phil\CssSanitizer\Sheet\StyleRule;
use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;

/**
 * Writes CSS back from component values, declarations and sanitized style sheets.
 *
 * Names are escaped as CSSOM serializes identifiers, strings are written between double quotes, URLs as `url("…")`,
 * numbers as they were written. Where two tokens written side by side would be read back as other tokens — an ident
 * followed by a number, a number followed by a unit-like ident — an empty comment separates them, as CSS Syntax Level 3,
 * section 9, requires. White space is written as one space. Reading the output again gives the same values.
 *
 * The output never holds `</` followed by a letter, the start of an end tag: a `<` in a name or a string is written as
 * the escape `\3c `, and a `<` delim is kept apart from a `/` that follows it by an empty comment, so that CSS written
 * inside a `<style>` element cannot close it.
 */
final class Serializer
{
    /**
     * Pairs that need a comment between them, by the kind of the first token: the kinds of the second.
     *
     * @var array<string, list<string>>
     */
    private const array SEPARATED = [
        'ident' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC', '('],
        'at-keyword' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC'],
        'hash' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC'],
        'dimension' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC'],
        'delim#' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC'],
        'delim-' => ['ident', 'function', 'url', 'bad-url', 'delim-', 'number', 'percentage', 'dimension', 'CDC'],
        'number' => ['ident', 'function', 'url', 'bad-url', 'number', 'percentage', 'dimension', 'delim%'],
        'delim@' => ['ident', 'function', 'url', 'bad-url', 'delim-'],
        'delim.' => ['number', 'percentage', 'dimension'],
        'delim+' => ['number', 'percentage', 'dimension'],
        'delim/' => ['delim*'],
        // Not in the table: kept apart so that the output never holds `</` and a letter, which could close the
        // `<style>` element the style sheet is written in.
        'delim<' => ['delim/'],
    ];

    /**
     * A sanitized style sheet: its `@import` rules, then one rule after the other, two spaces of indentation per level
     * of nesting.
     */
    public function styleSheet(StyleSheet $sheet): string
    {
        $parts = [implode('', array_map($this->import(...), $sheet->imports)), $this->rules($sheet->rules, 0)];

        return implode("\n", array_filter($parts, static fn (string $part): bool => '' !== $part));
    }

    private function import(ImportRule $import): string
    {
        $conditions = $this->componentValues($import->conditions);

        return '@import url('.self::string($import->url).')'.('' === $conditions ? '' : ' '.$conditions).";\n";
    }

    /**
     * Declarations as a `style` attribute holds them: `color: red; margin: 0`.
     *
     * @param list<Declaration> $declarations
     */
    public function declarations(array $declarations): string
    {
        return implode('; ', array_map($this->declaration(...), $declarations));
    }

    private function declaration(Declaration $declaration): string
    {
        return self::identifier($declaration->name).': '.$this->componentValues($declaration->value).($declaration->important ? ' !important' : '');
    }

    /**
     * @param list<ComponentValue> $values
     */
    public function componentValues(array $values): string
    {
        $css = '';
        $last = null;

        foreach ($values as $value) {
            if ($last instanceof Token && $this->needsComment($last, $value)) {
                $css .= '/**/';
            }

            $css .= $this->componentValue($value);
            // A function or a block ends on a closing token, which joins nothing that follows.
            $last = $value instanceof Token ? $value : null;
        }

        return $css;
    }

    /**
     * A name as CSSOM serializes an identifier.
     */
    public static function identifier(string $name): string
    {
        return self::escape($name, true);
    }

    /** A string between double quotes, as CSSOM serializes it. */
    public static function string(string $value): string
    {
        $css = '"';

        // Byte by byte: only ASCII needs escaping, and the bytes of other characters are written as they are.
        foreach (str_split($value) as $c) {
            $byte = \ord($c[0]);

            if ($byte <= 0x1F || 0x7F === $byte) {
                $css .= 0 === $byte ? "\u{FFFD}" : self::hexEscape($byte);
            } else {
                $css .= match ($c) {
                    '"', '\\' => '\\'.$c,
                    '<' => self::hexEscape($byte),
                    default => $c,
                };
            }
        }

        return $css.'"';
    }

    /**
     * @param list<StyleRule|GroupRule> $rules
     */
    private function rules(array $rules, int $depth): string
    {
        return implode("\n", array_map(fn (StyleRule|GroupRule $rule): string => $this->rule($rule, $depth), $rules));
    }

    private function rule(StyleRule|GroupRule $rule, int $depth): string
    {
        if ($rule instanceof StyleRule) {
            return $this->block(implode(', ', array_map($this->componentValues(...), $rule->selectors)), $rule->declarations, $rule->rules, $depth);
        }

        $prelude = $this->componentValues($rule->prelude);

        return $this->block('@'.$rule->name.('' === $prelude ? '' : ' '.$prelude), $rule->declarations, $rule->rules, $depth);
    }

    /**
     * @param list<Declaration>         $declarations
     * @param list<StyleRule|GroupRule> $rules
     */
    private function block(string $head, array $declarations, array $rules, int $depth): string
    {
        $indent = str_repeat('  ', $depth);

        if ([] === $declarations && [] === $rules) {
            return $indent.$head." {}\n";
        }

        $inner = str_repeat('  ', $depth + 1);
        $body = implode('', array_map(fn (Declaration $declaration): string => $inner.$this->declaration($declaration).";\n", $declarations));

        if ([] !== $rules) {
            $body .= ('' === $body ? '' : "\n").$this->rules($rules, $depth + 1);
        }

        return $indent.$head." {\n".$body.$indent."}\n";
    }

    private function componentValue(ComponentValue $value): string
    {
        if ($value instanceof FunctionValue) {
            return self::identifier($value->name).'('.$this->componentValues($value->arguments).')';
        }

        if ($value instanceof SimpleBlock) {
            return $value->opening->value.$this->componentValues($value->value).match ($value->opening) {
                TokenType::OpenCurly => '}',
                TokenType::OpenSquare => ']',
                default => ')',
            };
        }

        return match ($value->type) {
            TokenType::Ident => self::identifier($value->value),
            TokenType::AtKeyword => '@'.self::identifier($value->value),
            TokenType::Hash => '#'.($value->isId ? self::identifier($value->value) : self::escape($value->value, false)),
            TokenType::String, TokenType::BadString => self::string($value->value),
            TokenType::Url => 'url('.self::string($value->value).')',
            TokenType::BadUrl => 'url("")',
            TokenType::Delim => '\\' === $value->value ? "\\\n" : $value->value,
            TokenType::Percentage => $value->value.'%',
            TokenType::Dimension => $value->value.$this->unit($value->unit),
            default => $value->value,
        };
    }

    /** The unit of a dimension, its first letter escaped where the number would read it as an exponent. */
    private function unit(string $unit): string
    {
        $css = self::identifier($unit);

        if (1 === preg_match('/^[eE][+-]?[0-9]/', $unit)) {
            return self::hexEscape(\ord($unit[0])).substr($css, 1);
        }

        return $css;
    }

    /**
     * Escapes what a name cannot hold as it is: control characters by their code point, any other code point outside
     * a name by a backslash; for an identifier, also a leading digit, a digit after a leading hyphen, and a lone hyphen.
     */
    private static function escape(string $value, bool $asIdentifier): string
    {
        if ($asIdentifier && '-' === $value) {
            return '\-';
        }

        $css = '';

        // Byte by byte: the rules are about ASCII, and the bytes of other characters are name code points.
        foreach (str_split($value) as $index => $c) {
            $byte = \ord($c[0]);

            if ($byte <= 0x1F || 0x7F === $byte) {
                $css .= 0 === $byte ? "\u{FFFD}" : self::hexEscape($byte);
            } elseif ($asIdentifier && $c >= '0' && $c <= '9' && (0 === $index || (1 === $index && '-' === $value[0]))) {
                $css .= self::hexEscape($byte);
            } else {
                $css .= match (true) {
                    self::isNameCodePoint($c) => $c,
                    '<' === $c => self::hexEscape($byte),
                    default => '\\'.$c,
                };
            }
        }

        return $css;
    }

    private static function isNameCodePoint(string $c): bool
    {
        return \ord($c[0]) >= 0x80 || '-' === $c || '_' === $c || ($c >= '0' && $c <= '9') || ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z');
    }

    private static function hexEscape(int $codePoint): string
    {
        return '\\'.dechex($codePoint).' ';
    }

    /**
     * Whether two component values written side by side would read back as other tokens: the table of section 9,
     * less a signed number that cannot join what comes before it, so that `U+0000-00FF` stays as it is written — a
     * `+` joins nothing but a dimension whose unit would become an exponent, a `-` nothing after a number.
     */
    private function needsComment(Token $previous, ComponentValue $next): bool
    {
        if (!\in_array($this->kind($next), self::SEPARATED[$this->kind($previous)] ?? [], true)) {
            return false;
        }

        if (!$next instanceof Token || !\in_array($next->type, [TokenType::Number, TokenType::Percentage, TokenType::Dimension], true)) {
            return true;
        }

        return match ($next->value[0]) {
            '+' => TokenType::Dimension === $previous->type && 'e' === strtolower($previous->unit),
            '-' => TokenType::Number !== $previous->type,
            default => true,
        };
    }

    /** The kind of the first token of a component value, as the table of section 9 names it. */
    private function kind(ComponentValue $value): string
    {
        if ($value instanceof FunctionValue) {
            return 'function';
        }

        if ($value instanceof SimpleBlock) {
            return $value->opening->value;
        }

        return TokenType::Delim === $value->type ? 'delim'.$value->value : $value->type->value;
    }
}

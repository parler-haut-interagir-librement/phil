<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

/**
 * Why the sanitizer removed part of a style sheet.
 */
enum RemovalReason: string
{
    /** An `@import` rule the policy does not allow, or one that does not stand at the start of the style sheet. */
    case Import = 'import';
    /** An at-rule the policy does not allow, or one in a place where it cannot stand. */
    case AtRule = 'at_rule';
    /** The prelude of an at-rule — a media query, a condition, a page selector — the policy does not allow. */
    case Prelude = 'prelude';
    /** A selector the policy does not allow; the other selectors of its rule stay. */
    case Selector = 'selector';
    /** A property the policy does not allow, or a custom property when they are not allowed. */
    case Property = 'property';
    /** A property that runs code or binds behaviour, never allowed. */
    case DeniedProperty = 'denied_property';
    /** A function the policy does not allow, or one used where it cannot stand. */
    case Function = 'function';
    /** A URL the rewriter refused. */
    case Url = 'url';
    /** A URL whose scheme runs code. */
    case DangerousUrl = 'dangerous_url';
    /** A value holding a token no allowed value holds — a colon, a block, a bad string — or an empty value. */
    case Value = 'value';
    /** An `!important` flag, dropped when the policy does not keep them; the declaration stays. */
    case Important = 'important';
    /** Text CSS itself drops as a parse error: a declaration without its colon, a rule cut before its block. */
    case Syntax = 'syntax';
    /** An `@font-face` source the rule cannot use, or an `@font-face` rule left without a family or a source. */
    case FontSource = 'font_source';
    /** The source is longer than the policy accepts; it is refused whole. */
    case TooLong = 'too_long';
    /** The source nests blocks deeper than the policy accepts; it is refused whole. */
    case TooDeep = 'too_deep';
    /** A rule nested in a style rule beyond the levels of nesting the policy allows. */
    case Nesting = 'nesting';

    /** What was removed, in a few words, for a message. */
    public function describe(): string
    {
        return match ($this) {
            self::Import => 'an @import rule',
            self::AtRule => 'an at-rule that is not allowed',
            self::Prelude => 'a media query, condition or page selector that is not allowed',
            self::Selector => 'a selector that is not allowed',
            self::Property => 'a property that is not allowed',
            self::DeniedProperty => 'a property that runs code',
            self::Function => 'a function that is not allowed',
            self::Url => 'a URL that is not allowed',
            self::DangerousUrl => 'a URL that runs code',
            self::Value => 'a value that is not allowed',
            self::Important => 'an !important flag',
            self::Syntax => 'text that is not valid CSS',
            self::FontSource => 'a font source that cannot be used',
            self::TooLong => 'a style sheet that is too long',
            self::TooDeep => 'blocks nested too deep',
            self::Nesting => 'a rule nested deeper than allowed',
        };
    }
}

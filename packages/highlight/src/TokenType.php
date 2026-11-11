<?php

declare(strict_types=1);

namespace Phil\Highlight;

/**
 * What a token is, and so how a theme colours it. Every language writes the same types, so one theme serves them all:
 * the HTML class of a type is `hl-` and its value.
 */
enum TokenType: string
{
    /** A reserved word: `if`, `function`, `SELECT`, a CSS at-rule, a Dockerfile instruction. */
    case Keyword = 'keyword';
    /** A type name: `int`, `string`, `Vec`, a class written with a capital in the languages that say so. */
    case Type = 'type';
    /** A function or method that the language provides: `echo`, `print`, `len`. */
    case Builtin = 'builtin';
    /** A literal word: `true`, `false`, `null`, `None`, a Ruby symbol, a YAML boolean. */
    case Constant = 'constant';
    case Number = 'number';
    case String = 'string';
    /** A regular expression literal. */
    case Regex = 'regex';
    case Comment = 'comment';
    case Operator = 'operator';
    case Punctuation = 'punctuation';
    /** A variable with a sigil: `$name`, `@name`, a YAML anchor, a CSS custom property. */
    case Variable = 'variable';
    /** A name called: followed by `(`, a Rust macro, a Twig filter. */
    case Function = 'function';
    /** What speaks about the code: a preprocessor line, a decorator, an attribute, a shebang, PHP's tags, a doctype. */
    case Meta = 'meta';
    /** A markup tag's name. */
    case Tag = 'tag';
    /** A markup attribute's name. */
    case AttributeName = 'attr-name';
    /** A character reference of markup: `&amp;`, `&#38;`. */
    case Entity = 'entity';
    /** A key: a CSS property, a JSON or YAML key, an INI key. */
    case Property = 'property';
    /** A CSS selector. */
    case Selector = 'selector';
    /** An INI section, a diff hunk's header. */
    case Section = 'section';
    /** A Markdown heading. */
    case Heading = 'heading';
    /** Markdown emphasis. */
    case Emphasis = 'emphasis';
    /** Markdown strong emphasis. */
    case Strong = 'strong';
    /** A Markdown link or autolink. */
    case Link = 'link';
    /** Markdown code: a code span or a fenced block. */
    case Code = 'code';
    /** A line a diff adds. */
    case Inserted = 'inserted';
    /** A line a diff removes. */
    case Deleted = 'deleted';

    /**
     * The HTML class of the type: `hl-keyword`, `hl-attr-name`.
     */
    public function className(): string
    {
        return 'hl-'.$this->value;
    }
}

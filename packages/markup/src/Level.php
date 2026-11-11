<?php

declare(strict_types=1);

namespace Phil\Markup;

/**
 * How much of the HTML a text compiles to is kept: the purifier's policy.
 */
enum Level: string
{
    /** Inline emphasis only — bold, italic, underline, strikethrough, exponents, code, line breaks —: a name, a caption, a comment. */
    case Basic = 'basic';

    /** Basic, with paragraphs, links, lists, quotes, code blocks, images and rules: a message of a forum. */
    case Forum = 'forum';

    /** Every element the sanitizer counts as safe, headings and tables included: an article an editor writes. */
    case All = 'all';
}

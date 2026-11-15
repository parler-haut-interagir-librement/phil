<?php

declare(strict_types=1);

namespace Phil\Markup;

/**
 * How much of the HTML a text compiles to is kept: the purifier's policy.
 */
enum Level: string
{
    /**
     * Inline emphasis only — bold, italic, underline, strikethrough, exponents, code, line breaks —: a name, a caption,
     * a comment. The state of a checklist item is written as text.
     */
    case Basic = 'basic';

    /**
     * Basic, with paragraphs, links, lists, quotes, code blocks, images and rules: a message of a forum. The state of a
     * checklist item is written as text.
     */
    case Forum = 'forum';

    /**
     * Every element the sanitizer counts as safe, headings and tables included, and the disabled checkbox of a
     * checklist item: an article an editor writes.
     */
    case All = 'all';
}

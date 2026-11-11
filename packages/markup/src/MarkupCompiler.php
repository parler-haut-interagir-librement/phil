<?php

declare(strict_types=1);

namespace Phil\Markup;

use Phil\Markup\Exception\UnknownFormat;

/**
 * Turns a source into HTML. **The result is not safe to show**: it goes through an `HtmlPurifier` first; a compiler
 * only translates. A format is a case of `MarkupFormat`, or the name of a format an add-on brings.
 */
interface MarkupCompiler
{
    /**
     * @throws UnknownFormat when the compiler knows no format of that name
     */
    public function compile(string $source, MarkupFormat|string $format): string;
}

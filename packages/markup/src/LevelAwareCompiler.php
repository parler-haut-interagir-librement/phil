<?php

declare(strict_types=1);

namespace Phil\Markup;

use Phil\Markup\Exception\UnknownFormat;

/**
 * A compiler that knows the level its HTML will be purified at, and reads the source accordingly: raw HTML written in
 * Markdown is kept for `all`, where an editor writes it, and escaped below, where it would only be removed. `Markup`
 * gives the level to a compiler that implements this interface.
 */
interface LevelAwareCompiler extends MarkupCompiler
{
    /**
     * @throws UnknownFormat when the compiler knows no format of that name
     */
    public function compileFor(string $source, MarkupFormat|string $format, Level $level): string;
}

<?php

declare(strict_types=1);

namespace Phil\Markup;

use Phil\Markup\Exception\UnknownFormat;

/**
 * Compiles a source, then purifies the HTML at a level: what a page shows. A compiler that knows levels is told the
 * level the HTML will be purified at.
 */
final readonly class Markup
{
    public function __construct(
        private MarkupCompiler $compiler,
        private HtmlPurifier $purifier,
    ) {
    }

    /**
     * @throws UnknownFormat when the compiler knows no format of that name
     */
    public function toHtml(string $source, MarkupFormat|string $format, Level $level = Level::All): string
    {
        $html = $this->compiler instanceof LevelAwareCompiler ? $this->compiler->compileFor($source, $format, $level) : $this->compiler->compile($source, $format);

        return $this->purifier->purify($html, $level);
    }

    /**
     * @throws UnknownFormat when the compiler knows no format of that name
     */
    public function text(string $source, MarkupFormat|string $format, Level $level = Level::All): MarkedUpText
    {
        return new MarkedUpText($source, $format, $this->toHtml($source, $format, $level));
    }
}

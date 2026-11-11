<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\RawNormalizer;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * One reading of a wiki text: its new lines made `\n`, its blocks read by the engine with the wiki's syntax, then
 * resolved as the Markdown reader resolves its own — images placed, headings given an anchor, footnotes added at the
 * end — and normalized by `phil/ujo`.
 *
 * @internal
 */
final class Pipeline
{
    public static function read(Syntax $syntax, string $source, bool $rawHtml, ?Normalizer $normalizer, WikiLinks $links): Reading
    {
        $source = $syntax->prepare(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $source));
        $context = new Context($rawHtml);
        $inline = new InlineParser($context);
        $engine = new Engine(new Spans($context, $syntax, $links), $syntax, $inline, explode("\n", $source));
        $blocks = new Resolver($context, $inline)->document($engine->document());
        $normalizer ??= RawNormalizer::get();

        return new Reading($normalizer->normalize(new Document($blocks)), $engine->metadata);
    }
}

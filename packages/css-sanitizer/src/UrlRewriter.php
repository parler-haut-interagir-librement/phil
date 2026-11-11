<?php

declare(strict_types=1);

namespace Phil\CssSanitizer;

/**
 * Decides what each URL of a style sheet becomes: the URL to write in its place, or `null` to refuse it, which removes
 * the declaration — or, in an `@font-face` source list, the source — that holds it.
 *
 * The URL is given as written, its escapes resolved. A URL made of a fragment alone (`#chapter`) points into the
 * document and loads nothing; it never reaches the rewriter. A URL whose scheme runs code (`javascript:`) is refused
 * before the rewriter sees it, and so is any such URL the rewriter returns.
 */
interface UrlRewriter
{
    public function rewrite(string $url, UrlKind $kind): ?string;
}

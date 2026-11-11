<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Url;

use Closure;
use Override;
use Phil\CssSanitizer\UrlKind;
use Phil\CssSanitizer\UrlRewriter;

/**
 * A rewriter written as a closure: given the URL and what it loads, it returns the URL to write, or `null` to refuse.
 */
final readonly class CallbackRewriter implements UrlRewriter
{
    /**
     * @param Closure(string, UrlKind): ?string $callback
     */
    public function __construct(
        private Closure $callback,
    ) {
    }

    #[Override]
    public function rewrite(string $url, UrlKind $kind): ?string
    {
        return ($this->callback)($url, $kind);
    }
}

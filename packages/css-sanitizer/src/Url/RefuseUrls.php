<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Url;

use Override;
use Phil\CssSanitizer\UrlKind;
use Phil\CssSanitizer\UrlRewriter;

/**
 * Refuses every URL: the policy's rewriter until the application gives one.
 */
final readonly class RefuseUrls implements UrlRewriter
{
    #[Override]
    public function rewrite(string $url, UrlKind $kind): ?string
    {
        return null;
    }
}

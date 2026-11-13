<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\Asset;

use Override;
use Phil\PwaBundle\Application\Contract\AssetUrls;

/**
 * The address of an asset without the Asset component: its logical path under `$base`, not versioned.
 */
final readonly class PlainAssetUrls implements AssetUrls
{
    public function __construct(
        private string $base = '/',
    ) {
    }

    #[Override]
    public function url(string $path): string
    {
        return rtrim($this->base, '/').'/'.ltrim($path, '/');
    }
}

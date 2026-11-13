<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\Asset;

use Override;
use Phil\PwaBundle\Application\Contract\AssetUrls;
use Symfony\Component\Asset\Packages;

/**
 * The address of an asset from the Asset component: with AssetMapper, the versioned file it compiles.
 */
final readonly class PackagesAssetUrls implements AssetUrls
{
    public function __construct(
        private Packages $packages,
    ) {
    }

    #[Override]
    public function url(string $path): string
    {
        return $this->packages->getUrl($path);
    }
}

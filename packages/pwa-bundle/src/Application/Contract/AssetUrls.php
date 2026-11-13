<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Application\Contract;

/**
 * The public address of an asset from its logical path: with AssetMapper, the versioned file it compiles.
 */
interface AssetUrls
{
    public function url(string $path): string;
}

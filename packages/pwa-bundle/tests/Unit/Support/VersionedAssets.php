<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Support;

use Override;
use Phil\PwaBundle\Application\Contract\AssetUrls;

/**
 * Assets under /assets/, versioned by `-v1` before their extension, as AssetMapper names what it compiles.
 */
final readonly class VersionedAssets implements AssetUrls
{
    #[Override]
    public function url(string $path): string
    {
        return '/assets/'.preg_replace('/\.(\w+)$/', '-v1.$1', $path);
    }
}

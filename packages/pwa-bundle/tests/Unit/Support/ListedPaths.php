<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Support;

use Override;
use Phil\PwaBundle\Application\Contract\PrecacheProvider;

final readonly class ListedPaths implements PrecacheProvider
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private array $paths,
    ) {
    }

    #[Override]
    public function urls(): iterable
    {
        yield from $this->paths;
    }
}

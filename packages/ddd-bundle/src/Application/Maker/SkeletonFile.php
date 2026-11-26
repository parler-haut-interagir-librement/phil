<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Maker;

/**
 * One file a `make:ddd:*` command writes: its path, relative to the project directory, and its contents.
 */
final readonly class SkeletonFile
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit\Fixture;

use Override;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Application\Port\EpubStorage;

/**
 * A storage that cannot keep anything.
 */
final class FailingStorage implements EpubStorage
{
    #[Override]
    public function store(string $path, string $bytes): string
    {
        throw StorageFailed::because('The disk is full.');
    }
}

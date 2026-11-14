<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * A dispatcher whose listener fails.
 */
final class ThrowingDispatcher implements EventDispatcherInterface
{
    #[Override]
    public function dispatch(object $event): object
    {
        throw new RuntimeException('A listener failed.');
    }
}

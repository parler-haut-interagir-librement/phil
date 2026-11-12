<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\LabelDimension;

/**
 * A root built by a named constructor only: its constructor takes nothing, but is private.
 */
final class SealedRoot extends AbstractContentRoot
{
    private function __construct()
    {
    }

    public static function sealed(): self
    {
        return new self();
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return LabelDimension::class;
    }
}

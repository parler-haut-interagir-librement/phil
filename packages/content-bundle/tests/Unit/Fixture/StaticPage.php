<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;

/**
 * A root whose class name holds two words, for the default resource key. Never mapped.
 */
final class StaticPage extends AbstractContentRoot
{
    #[Override]
    public static function dimensionContentClass(): string
    {
        return PageDimension::class;
    }
}

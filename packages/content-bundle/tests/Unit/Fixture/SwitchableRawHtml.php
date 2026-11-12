<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;

/**
 * The raw HTML permission of whoever is at work, switched by the test as one user hands over to another.
 */
final class SwitchableRawHtml implements RawHtmlPermission
{
    public function __construct(
        public bool $granted = false,
    ) {
    }

    #[Override]
    public function isGranted(): bool
    {
        return $this->granted;
    }
}

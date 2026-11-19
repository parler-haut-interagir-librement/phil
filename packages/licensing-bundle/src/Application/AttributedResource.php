<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application;

use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;

/**
 * A resource of the attribution page, with its license as the catalog knows it, or `null` when it does not.
 */
final readonly class AttributedResource
{
    public function __construct(
        public Attribution $attribution,
        public ?License $license,
    ) {
    }
}

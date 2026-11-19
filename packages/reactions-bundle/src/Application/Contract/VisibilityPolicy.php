<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\Resource\ResourceReference;

/**
 * Who sees what of the reactions of a content.
 */
interface VisibilityPolicy
{
    public function of(ResourceReference $content): ReactionVisibility;
}

<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Domain\Status\Audience;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\Resource\ResourceReference;

/**
 * The same visibility for every content, as the configuration says.
 */
final readonly class ConfiguredVisibility implements VisibilityPolicy
{
    private ReactionVisibility $visibility;

    public function __construct(string $panel, string $counts, string $names)
    {
        $this->visibility = new ReactionVisibility(Audience::from($panel), Audience::from($counts), Audience::from($names));
    }

    #[Override]
    public function of(ResourceReference $content): ReactionVisibility
    {
        return $this->visibility;
    }
}

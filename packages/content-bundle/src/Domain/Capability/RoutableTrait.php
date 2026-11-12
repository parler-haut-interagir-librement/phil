<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\Slug\Slug;

/**
 * The implementation of `RoutableCapable`, without any persistence mapping. The slug is held as its string,
 * the form its column stores.
 *
 * @phpstan-require-implements RoutableCapable
 */
trait RoutableTrait
{
    private ?string $slug = null;

    public function getSlug(): ?Slug
    {
        return null === $this->slug ? null : Slug::fromString($this->slug);
    }

    public function setSlug(?Slug $slug): void
    {
        $this->slug = $slug?->value;
    }
}

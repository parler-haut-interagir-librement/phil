<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\Slug\Slug;

/**
 * `Sluggable`, mapped for a slug unique within a scope: the column `slug`, with no index of its own. The entity says
 * what the slug is made of, with `slugSource()`, and within what, with `slugScope()`; it declares the unique index on
 * the columns of the scope and the slug, the only thing that holds under concurrent inserts.
 *
 * @phpstan-require-implements Sluggable
 */
trait ScopedSluggableEntity
{
    #[ORM\Column(name: 'slug', type: Types::STRING, length: Slug::MAX_LENGTH, nullable: true)]
    protected ?string $slug = null;

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function sluggerName(): ?string
    {
        return null;
    }

    public function assignSlug(?Slug $slug): void
    {
        $this->slug = $slug?->value;
    }
}

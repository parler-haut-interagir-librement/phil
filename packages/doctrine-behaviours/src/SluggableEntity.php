<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\Slug\Slug;

/**
 * `Sluggable`, mapped: the column `slug`, with a unique index, the only thing that holds under concurrent inserts, and
 * no scope. The entity says what the slug is made of, with `slugSource()`. An entity whose slug is unique within a
 * scope takes `ScopedSluggableEntity` instead.
 *
 * @phpstan-require-implements Sluggable
 */
trait SluggableEntity
{
    #[ORM\Column(name: 'slug', type: Types::STRING, length: Slug::MAX_LENGTH, unique: true, nullable: true)]
    protected ?string $slug = null;

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function sluggerName(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function slugScope(): array
    {
        return [];
    }

    public function assignSlug(?Slug $slug): void
    {
        $this->slug = $slug?->value;
    }
}

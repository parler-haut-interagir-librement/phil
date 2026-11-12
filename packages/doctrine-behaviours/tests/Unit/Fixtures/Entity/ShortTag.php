<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\Slug\Slug;

/**
 * Sluggable by hand, with a short column and a slugger of its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'short_tag')]
class ShortTag implements Sluggable
{
    #[ORM\Column(length: 8, unique: true, nullable: true)]
    protected ?string $slug = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $name,
    ) {
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function slugSource(): string
    {
        return $this->name;
    }

    public function slugScope(): array
    {
        return [];
    }

    public function sluggerName(): ?string
    {
        return 'short';
    }

    public function assignSlug(?Slug $slug): void
    {
        $this->slug = $slug?->value;
    }
}

<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\ScopedSluggableEntity;

/**
 * An article whose slug is unique within its locale: the same title gives the same slug in two locales.
 */
#[ORM\Entity]
#[ORM\Table(name: 'article')]
#[ORM\UniqueConstraint(name: 'article_locale_slug', columns: ['locale', 'slug'])]
class Article implements Sluggable
{
    use ScopedSluggableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 16)]
        public string $locale,
        #[ORM\Column]
        public string $title,
    ) {
    }

    #[Override]
    public function slugSource(): string
    {
        return $this->title;
    }

    #[Override]
    public function slugScope(): array
    {
        return ['locale'];
    }
}

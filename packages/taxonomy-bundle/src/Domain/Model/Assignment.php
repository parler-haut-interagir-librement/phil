<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;
use Symfony\Component\Uid\Uuid;

/**
 * A tag or a category given to a resource: the term — `tag` or `category` — and its identifier, the resource by its
 * kind and identifier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_taxonomy_assignment')]
#[ORM\UniqueConstraint(columns: ['term_type', 'term_id', 'resource_kind', 'resource_id'])]
#[ORM\Index(columns: ['resource_kind', 'resource_id'])]
final class Assignment
{
    public const string TAG = 'tag';

    public const string CATEGORY = 'category';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'term_type', length: 8)]
        public readonly string $termType,
        #[ORM\Column(name: 'term_id', length: 36)]
        public readonly string $termId,
        #[ORM\Column(name: 'resource_kind', length: 64)]
        public readonly string $resourceKind,
        #[ORM\Column(name: 'resource_id', length: 255)]
        public readonly string $resourceId,
    ) {
    }

    public static function ofTag(Tag $tag, ResourceReference $resource): self
    {
        return new self(Uuid::v7()->toRfc4122(), self::TAG, $tag->id, $resource->key, $resource->id);
    }

    public static function ofCategory(Category $category, ResourceReference $resource): self
    {
        return new self(Uuid::v7()->toRfc4122(), self::CATEGORY, $category->id, $resource->key, $resource->id);
    }

    public function resource(): ResourceReference
    {
        return ResourceReference::of($this->resourceKind, $this->resourceId);
    }
}

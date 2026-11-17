<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * The root of a single kind of content (`phil_content.singles`), one row per kind: the primary key over the resource
 * key lets the database refuse a second root of the kind, which two creations at once would otherwise both write.
 *
 * `ContentManager::create()` writes it in the transaction that creates the root, and `ContentManager::delete()`
 * removes it with the root. The roots of the kinds that are not single have none.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final readonly class SingleRoot
{
    public const string TABLE = 'phil_content_single_root';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'resource_key', length: 64)]
        public string $resourceKey,
        #[ORM\Column(name: 'resource_id', length: 64)]
        public string $resourceId,
    ) {
    }

    public static function of(ContentReference $root): self
    {
        return new self($root->resourceKey, $root->resourceId);
    }

    public function root(): ContentReference
    {
        return ContentReference::of($this->resourceKey, $this->resourceId);
    }
}

<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Symfony\Component\Uid\Uuid;

/**
 * A category in a tree: its key, its parent, its place among its siblings, and its path from the root — the
 * identifiers of its ancestors and its own, `/a/b/c/` —, so that a whole branch is found by one prefix. Its names are
 * translations, kept apart.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_taxonomy_category')]
#[ORM\UniqueConstraint(columns: ['category_key'])]
#[ORM\Index(columns: ['path'])]
final class Category
{
    /** The deepest a category may be, a root being at depth 0: eleven levels, whose path fits its column. */
    public const int MAX_DEPTH = 10;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'category_key', length: 128)]
        private string $key,
        #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(name: 'parent', nullable: true, onDelete: 'RESTRICT')]
        private ?self $parent,
        #[ORM\Column(name: 'path', length: 420)]
        private string $path,
        #[ORM\Column(name: 'position')]
        private int $position,
    ) {
    }

    /**
     * @throws InvalidTaxonomy when the key is longer than 128 characters, or the parent too deep
     */
    public static function create(Slug $key, ?self $parent = null, int $position = 0): self
    {
        $id = Uuid::v7()->toRfc4122();
        // The path and the position are the move's.
        $category = new self($id, self::checked($key), null, '', $position);
        $category->moveTo($parent, $position);

        return $category;
    }

    /**
     * Moves the category, and so its branch, under another parent or to the root.
     *
     * @param int $height how many levels the category's branch holds below it: its deepest descendant must stay within
     *                    `MAX_DEPTH` too
     *
     * @throws InvalidTaxonomy when the parent is the category itself or one of its descendants, or too deep
     */
    public function moveTo(?self $parent, int $position = 0, int $height = 0): void
    {
        if ($parent instanceof self && \in_array($this->id, explode('/', $parent->path), true)) {
            throw InvalidTaxonomy::because('A category cannot move under itself or one of its descendants.');
        }

        if (($parent instanceof self ? $parent->depth() + 1 : 0) + $height > self::MAX_DEPTH) {
            throw InvalidTaxonomy::because(\sprintf('A category is at depth %d at most, a root being at depth 0.', self::MAX_DEPTH));
        }

        $this->parent = $parent;
        $this->path = ($parent->path ?? '/').$this->id.'/';
        $this->position = $position;
    }

    /**
     * Recomputes the path from the parent's, after the parent moved.
     */
    public function followParent(): void
    {
        $this->path = ($this->parent->path ?? '/').$this->id.'/';
    }

    /**
     * @throws InvalidTaxonomy when the key is longer than 128 characters
     */
    public function rekey(Slug $key): void
    {
        $this->key = self::checked($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function depth(): int
    {
        return substr_count($this->path, '/') - 2;
    }

    public function isAncestorOf(self $other): bool
    {
        return $other->path !== $this->path && str_starts_with($other->path, $this->path);
    }

    private static function checked(Slug $key): string
    {
        if (\strlen($key->value) > 128) {
            throw InvalidTaxonomy::because(\sprintf('The key of a category is 128 characters or fewer; "%s" is not.', $key));
        }

        return $key->value;
    }
}

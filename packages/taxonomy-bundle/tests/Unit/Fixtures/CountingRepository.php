<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit\Fixtures;

use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;

/**
 * A repository that counts the reads it passes on, each one a query, and the saves, each one a flush.
 */
final class CountingRepository implements TaxonomyRepository
{
    /** @var array<string, int> by method */
    public array $reads = [];

    /** @var list<int> how many objects each save wrote, each one a flush */
    public array $saves = [];

    public function __construct(
        private readonly TaxonomyRepository $repository,
    ) {
    }

    public function save(Tag|Category|CategoryTranslation|Assignment ...$objects): void
    {
        $this->saves[] = \count($objects);
        $this->repository->save(...$objects);
    }

    public function remove(Tag|Category|CategoryTranslation|Assignment ...$objects): void
    {
        $this->repository->remove(...$objects);
    }

    public function tag(string $id): ?Tag
    {
        return $this->count(__FUNCTION__, $this->repository->tag($id));
    }

    public function tags(array $ids): array
    {
        return $this->count(__FUNCTION__, $this->repository->tags($ids));
    }

    public function tagBySlug(string $slug): ?Tag
    {
        return $this->count(__FUNCTION__, $this->repository->tagBySlug($slug));
    }

    public function category(string $id): ?Category
    {
        return $this->count(__FUNCTION__, $this->repository->category($id));
    }

    public function categoriesWithIds(array $ids): array
    {
        return $this->count(__FUNCTION__, $this->repository->categoriesWithIds($ids));
    }

    public function categoryByKey(string $key): ?Category
    {
        return $this->count(__FUNCTION__, $this->repository->categoryByKey($key));
    }

    public function categories(): array
    {
        return $this->count(__FUNCTION__, $this->repository->categories());
    }

    public function branchIds(Category $category): array
    {
        return $this->count(__FUNCTION__, $this->repository->branchIds($category));
    }

    public function translations(Category $category): array
    {
        return $this->count(__FUNCTION__, $this->repository->translations($category));
    }

    public function translationsOf(array $categories, ?array $locales = null): array
    {
        return $this->count(__FUNCTION__, $this->repository->translationsOf($categories, $locales));
    }

    public function assignments(ResourceReference $resource, string $termType): array
    {
        return $this->count(__FUNCTION__, $this->repository->assignments($resource, $termType));
    }

    public function resourcesWith(string $termType, array $termIds, ?string $resourceKind = null): array
    {
        return $this->count(__FUNCTION__, $this->repository->resourcesWith($termType, $termIds, $resourceKind));
    }

    public function reassign(string $termType, string $fromId, string $toId): void
    {
        $this->repository->reassign($termType, $fromId, $toId);
    }

    /**
     * @template T
     *
     * @param T $result
     *
     * @return T
     */
    private function count(string $method, mixed $result): mixed
    {
        $this->reads[$method] = ($this->reads[$method] ?? 0) + 1;

        return $result;
    }
}

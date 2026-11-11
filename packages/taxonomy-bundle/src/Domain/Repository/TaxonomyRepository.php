<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Repository;

use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;

interface TaxonomyRepository
{
    public function save(Tag|Category|CategoryTranslation|Assignment ...$objects): void;

    public function remove(Tag|Category|CategoryTranslation|Assignment ...$objects): void;

    public function tag(string $id): ?Tag;

    /**
     * The tags with these identifiers, at once; those that are gone left out.
     *
     * @param list<string> $ids
     *
     * @return list<Tag>
     */
    public function tags(array $ids): array;

    public function tagBySlug(string $slug): ?Tag;

    public function category(string $id): ?Category;

    /**
     * The categories with these identifiers, at once, in that order; those that are gone left out.
     *
     * @param list<string> $ids
     *
     * @return list<Category>
     */
    public function categoriesWithIds(array $ids): array;

    public function categoryByKey(string $key): ?Category;

    /**
     * Every category, by depth, then position, then key.
     *
     * @return list<Category>
     */
    public function categories(): array;

    /**
     * The identifiers of a category and of its descendants, every level.
     *
     * @return list<string>
     */
    public function branchIds(Category $category): array;

    /**
     * @return list<CategoryTranslation>
     */
    public function translations(Category $category): array;

    /**
     * The translations of several categories, at once; in these locales only, unless `null`.
     *
     * @param list<Category>    $categories
     * @param list<string>|null $locales
     *
     * @return list<CategoryTranslation>
     */
    public function translationsOf(array $categories, ?array $locales = null): array;

    /**
     * The assignments of one term type to a resource.
     *
     * @return list<Assignment>
     */
    public function assignments(ResourceReference $resource, string $termType): array;

    /**
     * The resources given these terms, of one kind when one is given.
     *
     * @param list<string> $termIds
     *
     * @return list<ResourceReference>
     */
    public function resourcesWith(string $termType, array $termIds, ?string $resourceKind = null): array;

    /**
     * Gives another term every resource one term has, once; flushed with the next save or removal.
     */
    public function reassign(string $termType, string $fromId, string $toId): void;
}

<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Benchmark\Fixture;

use Doctrine\ORM\EntityManager;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;

/**
 * Writes a tree of categories in one flush, each named in French and English, so that a large tree is set up quickly.
 */
final class CategoryTree
{
    /**
     * Ten roots and five children per category, three or four levels deep; flat, every category a root.
     *
     * @return list<Category> in the order they were created, parents first
     */
    public static function build(EntityManager $entityManager, int $count, bool $flat = false, ?Category $under = null, string $prefix = 'category'): array
    {
        $categories = [];

        for ($i = 0; $i < $count; ++$i) {
            $parent = $flat || $i < 10 ? $under : $categories[intdiv($i - 10, 5)];
            $category = Category::create(Slug::fromString($prefix.'-'.$i), $parent, $i % 7);
            $entityManager->persist($category);
            $entityManager->persist(CategoryTranslation::of($category, 'fr', 'Catégorie '.$i));
            $entityManager->persist(CategoryTranslation::of($category, 'en', 'Category '.$i));
            $categories[] = $category;
        }

        $entityManager->flush();

        return $categories;
    }
}

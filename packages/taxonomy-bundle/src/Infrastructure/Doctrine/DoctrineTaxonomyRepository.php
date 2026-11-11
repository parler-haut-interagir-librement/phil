<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;

/**
 * Tags, categories, their translations and their assignments with Doctrine.
 */
final readonly class DoctrineTaxonomyRepository implements TaxonomyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Tag|Category|CategoryTranslation|Assignment ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Tag|Category|CategoryTranslation|Assignment ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->remove($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function tag(string $id): ?Tag
    {
        return $this->entityManager->find(Tag::class, $id);
    }

    #[Override]
    public function tags(array $ids): array
    {
        return [] === $ids ? [] : $this->entityManager->getRepository(Tag::class)->findBy(['id' => $ids]);
    }

    #[Override]
    public function tagBySlug(string $slug): ?Tag
    {
        return $this->entityManager->getRepository(Tag::class)->findOneBy(['slug' => $slug]);
    }

    #[Override]
    public function category(string $id): ?Category
    {
        return $this->entityManager->find(Category::class, $id);
    }

    #[Override]
    public function categoriesWithIds(array $ids): array
    {
        $found = [];

        foreach ([] === $ids ? [] : $this->entityManager->getRepository(Category::class)->findBy(['id' => $ids]) as $category) {
            $found[$category->id] = $category;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?Category => $found[$id] ?? null, $ids)));
    }

    #[Override]
    public function categoryByKey(string $key): ?Category
    {
        return $this->entityManager->getRepository(Category::class)->findOneBy(['key' => $key]);
    }

    #[Override]
    public function categories(): array
    {
        /** @var list<Category> */
        return $this->entityManager->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->orderBy('LENGTH(category.path)', 'ASC')
            ->addOrderBy('category.position', 'ASC')
            ->addOrderBy('category.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function branchIds(Category $category): array
    {
        /** @var list<string> */
        return $this->entityManager->createQueryBuilder()
            ->select('category.id')
            ->from(Category::class, 'category')
            ->where('category.path LIKE :branch')
            ->setParameter('branch', addcslashes($category->path(), '%_').'%')
            ->getQuery()
            ->getSingleColumnResult();
    }

    #[Override]
    public function translations(Category $category): array
    {
        return $this->entityManager->getRepository(CategoryTranslation::class)->findBy(['category' => $category]);
    }

    #[Override]
    public function translationsOf(array $categories, ?array $locales = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('translation')
            ->from(CategoryTranslation::class, 'translation')
            ->where('translation.category IN (:categories)')
            ->setParameter('categories', $categories);

        if (null !== $locales) {
            $query->andWhere('translation.locale IN (:locales)')->setParameter('locales', $locales);
        }

        /** @var list<CategoryTranslation> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function assignments(ResourceReference $resource, string $termType): array
    {
        return $this->entityManager->getRepository(Assignment::class)->findBy(['termType' => $termType, 'resourceKind' => $resource->key, 'resourceId' => $resource->id]);
    }

    #[Override]
    public function resourcesWith(string $termType, array $termIds, ?string $resourceKind = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT assignment.resourceKind, assignment.resourceId')
            ->from(Assignment::class, 'assignment')
            ->where('assignment.termType = :type')
            ->andWhere('assignment.termId IN (:ids)')
            ->setParameter('type', $termType)
            ->setParameter('ids', $termIds)
            ->orderBy('assignment.resourceKind', 'ASC')
            ->addOrderBy('assignment.resourceId', 'ASC');

        if (null !== $resourceKind) {
            $query->andWhere('assignment.resourceKind = :kind')->setParameter('kind', $resourceKind);
        }

        /** @var list<array{resourceKind: string, resourceId: string}> $rows */
        $rows = $query->getQuery()->getArrayResult();

        return array_map(static fn (array $row): ResourceReference => ResourceReference::of($row['resourceKind'], $row['resourceId']), $rows);
    }

    #[Override]
    public function reassign(string $termType, string $fromId, string $toId): void
    {
        $repository = $this->entityManager->getRepository(Assignment::class);
        // A term's identifier is a UUID: the type only says which assignment to create.
        $kept = array_flip(array_map(static fn (Assignment $assignment): string => $assignment->resourceKind.'|'.$assignment->resourceId, $repository->findBy(['termId' => $toId])));

        $into = Assignment::CATEGORY === $termType ? $this->category($toId) : $this->tag($toId);

        foreach ($repository->findBy(['termId' => $fromId]) as $assignment) {
            $resource = $assignment->resource();
            $this->entityManager->remove($assignment);

            if (isset($kept[$resource->key.'|'.$resource->id])) {
                continue;
            }

            if ($into instanceof Category) {
                $this->entityManager->persist(Assignment::ofCategory($into, $resource));
            } elseif ($into instanceof Tag) {
                $this->entityManager->persist(Assignment::ofTag($into, $resource));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Override;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;

/**
 * `RouteRepository` on the Doctrine ORM: a lookup by slug reads the unique index
 * `(resource_key, locale, slug)`, a lookup by content the index `(resource_key, resource_id, locale)`.
 */
final readonly class DoctrineRouteRepository implements RouteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findBySlug(string $resourceKey, Locale $locale, Slug $slug): ?Route
    {
        return $this->repository()->findOneBy(['resourceKey' => $resourceKey, 'locale' => $locale, 'slug' => $slug->value]);
    }

    #[Override]
    public function findCurrent(ContentReference $content, Locale $locale): ?Route
    {
        return $this->repository()->findOneBy([
            'resourceKey' => $content->resourceKey,
            'resourceId' => $content->resourceId,
            'locale' => $locale,
            'history' => false,
        ]);
    }

    /**
     * On the index `(resource_key, resource_id, locale)`, the identifiers in one `IN` list.
     *
     * @param list<string> $resourceIds
     *
     * @return list<Route>
     */
    #[Override]
    public function findCurrentAmong(string $resourceKey, Locale $locale, array $resourceIds): array
    {
        if ([] === $resourceIds) {
            return [];
        }

        return $this->repository()->findBy([
            'resourceKey' => $resourceKey,
            'resourceId' => $resourceIds,
            'locale' => $locale,
            'history' => false,
        ]);
    }

    /**
     * @return list<Route>
     */
    #[Override]
    public function findAllOf(ContentReference $content): array
    {
        return $this->repository()->findBy(
            ['resourceKey' => $content->resourceKey, 'resourceId' => $content->resourceId],
            ['locale' => 'ASC', 'history' => 'ASC', 'slug' => 'ASC'],
        );
    }

    #[Override]
    public function add(Route $route): void
    {
        $this->entityManager->persist($route);
    }

    #[Override]
    public function remove(Route $route): void
    {
        $this->entityManager->remove($route);
    }

    /**
     * @return EntityRepository<Route>
     */
    private function repository(): EntityRepository
    {
        return $this->entityManager->getRepository(Route::class);
    }
}

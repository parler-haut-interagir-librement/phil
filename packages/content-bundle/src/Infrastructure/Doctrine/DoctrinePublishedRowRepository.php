<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\PublishedRowRepository;
use Phil\ContentBundle\Domain\ValueObject\PublishedRow;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;

/**
 * `PublishedRowRepository` on the Doctrine ORM: two queries per page of rows, whatever its size.
 *
 * 1. The localized live rows of the locale, each in the highest live version of that locale for its root (a
 *    correlated `MAX` over the `(root_id, stage)` prefix of the unique index), with their root fetched in the same
 *    query, filtered, ordered and cut as the query says.
 * 2. The shared live rows of the same roots and versions, by the roots' identifiers and the versions found, each in
 *    one `IN` list.
 *
 * The roots' identifiers are bound as their database values (`RootIdentifiers`), so a root identified by an
 * object type of the application is bound as its own DBAL type stores it.
 */
final readonly class DoctrinePublishedRowRepository implements PublishedRowRepository
{
    private RootIdentifiers $identifiers;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContentRoots $roots,
    ) {
        $this->identifiers = new RootIdentifiers($entityManager);
    }

    /**
     * @return list<PublishedRow>
     */
    #[Override]
    public function findPublished(PublishedRowQuery $query): array
    {
        $rootClass = $this->roots->classOf($query->resourceKey);
        $rowClass = $rootClass::dimensionContentClass();

        $identifiers = null === $query->resourceIds ? null : array_values(array_filter(
            array_map(fn (string $id): mixed => $this->identifiers->fromString($rootClass, $id), $query->resourceIds),
            static fn (mixed $identifier): bool => null !== $identifier,
        ));

        if ([] === $identifiers || ($query->withSlug && !is_subclass_of($rowClass, RoutableCapable::class))) {
            return [];
        }

        /** @var list<AbstractDimensionContent> $localized */
        $localized = $this->localizedRows($query, $rootClass, $rowClass, $identifiers)->getQuery()->getResult();

        // An empty page needs no shared rows: no second query.
        if ([] === $localized) {
            return $localized;
        }

        $shared = $this->sharedRows($rootClass, $rowClass, $localized);

        return array_map(
            static fn (AbstractDimensionContent $row): PublishedRow => new PublishedRow($row, $shared[spl_object_id($row->root())][$row->dimension()->version->number] ?? null),
            $localized,
        );
    }

    /**
     * @param class-string<AbstractContentRoot>      $rootClass
     * @param class-string<AbstractDimensionContent> $rowClass
     * @param non-empty-list<mixed>|null             $identifiers the roots to keep, `null` for every root
     */
    private function localizedRows(PublishedRowQuery $query, string $rootClass, string $rowClass, ?array $identifiers): QueryBuilder
    {
        $rootId = 'r.'.$this->entityManager->getClassMetadata($rootClass)->getSingleIdentifierFieldName();
        $root = DimensionContentMappingListener::ROOT_FIELD;
        $latest = $this->entityManager->createQueryBuilder()
            ->select('MAX(m.version)')
            ->from($rowClass, 'm')
            ->where(\sprintf('m.%1$s = l.%1$s', $root))
            ->andWhere('m.stage = :stage')
            ->andWhere('m.localeKey = :locale');

        $builder = $this->entityManager->createQueryBuilder()
            ->select('l', 'r')
            ->from($rowClass, 'l')
            ->join('l.'.$root, 'r')
            ->where('l.stage = :stage')
            ->andWhere('l.localeKey = :locale')
            ->andWhere('l.version = ('.$latest->getDQL().')')
            ->setParameter('stage', Stage::Live, StageType::NAME)
            ->setParameter('locale', $query->locale->value)
            ->setFirstResult($query->offset)
            ->setMaxResults($query->limit);

        $this->filter($builder, $query, $rootClass, $rootId, $identifiers);

        if ($query->withSlug) {
            $builder->andWhere('l.slug IS NOT NULL');
        }

        if (PublishedOrder::LatestPublished === $query->order && is_subclass_of($rowClass, WorkflowCapable::class)) {
            // NULL sorts first on PostgreSQL and last on MySQL and SQLite in a descending order: the hidden flag
            // puts the rows never published last everywhere.
            $builder->addSelect('CASE WHEN l.workflowPublished IS NULL THEN 1 ELSE 0 END AS HIDDEN never_published')
                ->addOrderBy('never_published', 'ASC')
                ->addOrderBy('l.workflowPublished', 'DESC');
        }

        if (PublishedOrder::Slug === $query->order && is_subclass_of($rowClass, RoutableCapable::class)) {
            $builder->addOrderBy('l.slug', 'ASC');
        }

        return $builder->addOrderBy($rootId, 'ASC');
    }

    /**
     * @param class-string<AbstractContentRoot> $rootClass
     * @param non-empty-list<mixed>|null        $identifiers
     *
     * @throws InvalidContentMapping when a criterion names a field the root does not map
     */
    private function filter(QueryBuilder $builder, PublishedRowQuery $query, string $rootClass, string $rootId, ?array $identifiers): void
    {
        $metadata = $this->entityManager->getClassMetadata($rootClass);

        foreach (array_keys($query->rootCriteria) as $index => $field) {
            if (!$metadata->hasField($field) || $metadata->isIdentifier($field)) {
                throw InvalidContentMapping::because(\sprintf('The root "%s" maps no field "%s" to select published rows by.', $rootClass, $field));
            }

            $builder->andWhere(\sprintf('r.%s = :criterion%d', $field, $index))->setParameter('criterion'.$index, $query->rootCriteria[$field]);
        }

        if (null !== $identifiers) {
            [$values, $type] = $this->identifiers->bindable($rootClass, $identifiers);
            $builder->andWhere($rootId.' IN (:resourceIds)')->setParameter('resourceIds', $values, $type);
        }
    }

    /**
     * The shared rows of the versions the localized rows were found in, by root and version.
     *
     * @param class-string<AbstractContentRoot>      $rootClass
     * @param class-string<AbstractDimensionContent> $rowClass
     * @param list<AbstractDimensionContent>         $localized
     *
     * @return array<int, array<int, AbstractDimensionContent>> by root object, then version
     */
    private function sharedRows(string $rootClass, string $rowClass, array $localized): array
    {
        $roots = [];
        $versions = [];

        foreach ($localized as $row) {
            $roots[spl_object_id($row->root())] = $row->root();
            $versions[$row->dimension()->version->number] = $row->dimension()->version->number;
        }

        $metadata = $this->entityManager->getClassMetadata($rootClass);
        $identifiers = array_map(static fn (object $root): mixed => array_first($metadata->getIdentifierValues($root)), array_values($roots));
        [$values, $type] = $this->identifiers->bindable($rootClass, $identifiers);

        /** @var list<AbstractDimensionContent> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from($rowClass, 's')
            ->where('s.stage = :stage')
            ->andWhere('s.localeKey = :shared')
            ->andWhere(\sprintf('IDENTITY(s.%s) IN (:roots)', DimensionContentMappingListener::ROOT_FIELD))
            ->andWhere('s.version IN (:versions)')
            ->setParameter('stage', Stage::Live, StageType::NAME)
            ->setParameter('shared', AbstractDimensionContent::UNLOCALIZED_KEY)
            ->setParameter('roots', $values, $type)
            ->setParameter('versions', array_values($versions), ArrayParameterType::INTEGER)
            ->getQuery()
            ->getResult();

        $shared = [];

        foreach ($rows as $row) {
            $shared[spl_object_id($row->root())][$row->dimension()->version->number] = $row;
        }

        return $shared;
    }
}

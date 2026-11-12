<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * `DimensionContentRepository` on the Doctrine ORM. Rows added or removed are written when the
 * `ContentTransaction` flushes.
 *
 * The row class is the one the root names (`AbstractContentRoot::dimensionContentClass()`), so one adapter
 * serves every root of the application. Each lookup reads a prefix of the unique index the mapping listener
 * declares; the unlocalized row is found by its sentinel in `locale_key`, never by `locale IS NULL`, so it
 * reads the same index.
 */
final readonly class DoctrineDimensionContentRepository implements DimensionContentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(ContentRoot $root, DimensionKey $dimension): ?AbstractDimensionContent
    {
        return $this->repositoryOf($root)->findOneBy([
            DimensionContentMappingListener::ROOT_FIELD => $root,
            'stage' => $dimension->stage,
            'version' => $dimension->version,
            'localeKey' => $dimension->locale->value ?? AbstractDimensionContent::UNLOCALIZED_KEY,
        ]);
    }

    /**
     * The sentinel `*` sorts before any letter, so ordering by `locale_key` puts the unlocalized row first.
     *
     * @return list<AbstractDimensionContent>
     */
    #[Override]
    public function findAll(ContentRoot $root, Stage $stage, Version $version): array
    {
        return $this->repositoryOf($root)->findBy(
            [DimensionContentMappingListener::ROOT_FIELD => $root, 'stage' => $stage, 'version' => $version],
            ['localeKey' => 'ASC'],
        );
    }

    /**
     * The first row by descending version, read through the persister rather than DQL: the persister binds the
     * root by the type of the root's own identifier, which a DQL parameter would have to be told by hand. On
     * the unique index, the lookup reads one entry of the `(root_id, stage)` prefix.
     */
    #[Override]
    public function latestVersion(ContentRoot $root, Stage $stage): ?Version
    {
        return $this->repositoryOf($root)->findOneBy(
            [DimensionContentMappingListener::ROOT_FIELD => $root, 'stage' => $stage],
            ['version' => 'DESC'],
        )?->dimension()->version;
    }

    /**
     * The first row of the locale by descending version; on the unique index, a lookup of the
     * `(root_id, stage)` prefix filtered by `locale_key`.
     */
    #[Override]
    public function latestLocalizedVersion(ContentRoot $root, Stage $stage, Locale $locale): ?Version
    {
        return $this->repositoryOf($root)->findOneBy(
            [DimensionContentMappingListener::ROOT_FIELD => $root, 'stage' => $stage, 'localeKey' => $locale->value],
            ['version' => 'DESC'],
        )?->dimension()->version;
    }

    /**
     * On the unique index, the `(root_id, stage)` prefix filtered by `locale_key`.
     *
     * @return list<AbstractDimensionContent>
     */
    #[Override]
    public function findVersions(ContentRoot $root, Stage $stage, Locale $locale): array
    {
        return $this->repositoryOf($root)->findBy(
            [DimensionContentMappingListener::ROOT_FIELD => $root, 'stage' => $stage, 'localeKey' => $locale->value],
            ['version' => 'DESC'],
        );
    }

    #[Override]
    public function add(DimensionContent $row): void
    {
        $this->entityManager->persist($row);
    }

    #[Override]
    public function remove(DimensionContent $row): void
    {
        $this->entityManager->remove($row);
    }

    /**
     * @return EntityRepository<AbstractDimensionContent>
     */
    private function repositoryOf(ContentRoot $root): EntityRepository
    {
        if (!$root instanceof AbstractContentRoot) {
            throw InvalidContentMapping::because(\sprintf('The Doctrine repository stores the rows of an %s; "%s" is not one.', AbstractContentRoot::class, $root::class));
        }

        return $this->entityManager->getRepository($root::dimensionContentClass());
    }
}

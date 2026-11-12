<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Repository;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * Loads and stores the dimension rows of a root: one dimension, the rows of one stage and version, the latest
 * version of a stage or of one locale in a stage, and the rows added or removed by editing and publishing.
 *
 * This is what replaces a collection on the root: a row points to its root, and a lookup reads exactly the
 * rows it needs. `Infrastructure\Doctrine\DoctrineDimensionContentRepository` is the Doctrine adapter.
 */
interface DimensionContentRepository
{
    /**
     * The row of `$root` whose dimension equals `$dimension`, or `null` when there is none.
     *
     * An unlocalized key finds only the unlocalized row: no locale stands in for it.
     */
    public function find(ContentRoot $root, DimensionKey $dimension): ?DimensionContent;

    /**
     * Every row of `$root` in one stage and version: the unlocalized row first when there is one, then the
     * localized rows ordered by locale. What a fallback chain resolves against, and what publishing copies.
     *
     * @return list<DimensionContent>
     */
    public function findAll(ContentRoot $root, Stage $stage, Version $version): array;

    /**
     * The highest version that has at least one row of `$root` in `$stage`, localized or not, or `null` when
     * the root has no row in that stage.
     */
    public function latestVersion(ContentRoot $root, Stage $stage): ?Version;

    /**
     * The highest version that has a row of `$locale` in `$stage`, or `null` when there is none. Locales are
     * published one at a time, so the live rows of two locales may belong to two versions: this is the version
     * a reader of `$locale` is served.
     */
    public function latestLocalizedVersion(ContentRoot $root, Stage $stage, Locale $locale): ?Version;

    /**
     * Every row of `$locale` of `$root` in `$stage`, one per version, the latest version first: a locale's history,
     * in one lookup.
     *
     * @return list<DimensionContent>
     */
    public function findVersions(ContentRoot $root, Stage $stage, Locale $locale): array;

    /**
     * Stores a new row; it is written when the caller's transaction flushes.
     */
    public function add(DimensionContent $row): void;

    /**
     * Deletes a stored row when the caller's transaction flushes.
     */
    public function remove(DimensionContent $row): void;
}

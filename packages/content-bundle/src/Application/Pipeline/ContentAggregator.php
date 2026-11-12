<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Version;

/**
 * Reads one dimension of a root as a single row: the unlocalized row, with the localized row merged over it.
 *
 * The rows are loaded through the repository, never through the root, and the result is a fresh row from
 * `ContentRoot::createDimensionContent()`: merging never writes onto a stored row.
 */
final readonly class ContentAggregator
{
    /**
     * @var array<Merger>
     */
    private array $mergers;

    /**
     * @param iterable<Merger> $mergers read once, here: a localized key walks them once per source row, and a
     *                                  generator cannot be walked twice
     */
    public function __construct(
        private DimensionContentRepository $repository,
        iterable $mergers,
    ) {
        $this->mergers = [...$mergers];
    }

    /**
     * @throws ContentNotFound when the localized row of a localized key is missing, or the row of an
     *                         unlocalized key; a missing shared row alone is not an error
     */
    public function aggregate(ContentRoot $root, DimensionKey $dimension): DimensionContent
    {
        if (!$dimension->isLocalized()) {
            $unlocalized = $this->repository->find($root, $dimension);

            if (!$unlocalized instanceof DimensionContent) {
                throw ContentNotFound::forDimension($dimension);
            }

            return $this->merge($root, $dimension, [$unlocalized]);
        }

        $localized = $this->repository->find($root, $dimension);

        if (!$localized instanceof DimensionContent) {
            throw ContentNotFound::forDimension($dimension);
        }

        return $this->mergeOverShared($root, $localized);
    }

    /**
     * The first locale of the chain that has a row, merged. The merged row's dimension says which locale was
     * served, so the caller can tell whether it left the requested language.
     *
     * The localized row found while walking the chain is the one merged: it is not read a second time.
     *
     * @throws ContentNotFound when no locale of the chain has a row
     */
    public function aggregateAlong(ContentRoot $root, FallbackChain $chain, Stage $stage, Version $version): DimensionContent
    {
        foreach ($chain->locales as $locale) {
            $localized = $this->repository->find($root, DimensionKey::localized($locale, $stage, $version));

            if ($localized instanceof DimensionContent) {
                return $this->mergeOverShared($root, $localized);
            }
        }

        throw ContentNotFound::alongChain($chain, $stage, $version);
    }

    /**
     * A localized row merged over the shared row of its version, both already loaded — by a
     * `PublishedRowRepository`, which reads them for many roots at once. The localized row's dimension is the
     * merged row's.
     *
     * @throws InvalidDimension when the localized row is not localized, or the shared row is not the unlocalized
     *                          row of the same root, stage and version
     */
    public function mergeRows(DimensionContent $localized, ?DimensionContent $shared): DimensionContent
    {
        $dimension = $localized->dimension();

        if (!$dimension->isLocalized()) {
            throw InvalidDimension::because(\sprintf('Rows are merged over a localized row; "%s" is not one.', $dimension->toString()));
        }

        if (!$shared instanceof DimensionContent) {
            return $this->merge($localized->root(), $dimension, [$localized]);
        }

        if ($shared->root() !== $localized->root() || !$shared->dimension()->equals($dimension->unlocalizedCounterpart())) {
            throw InvalidDimension::because(\sprintf('The shared row "%s" is not the counterpart of "%s" under the same root.', $shared->dimension()->toString(), $dimension->toString()));
        }

        return $this->merge($localized->root(), $dimension, [$shared, $localized]);
    }

    /**
     * The shared row, when there is one, then the localized row already loaded.
     */
    private function mergeOverShared(ContentRoot $root, DimensionContent $localized): DimensionContent
    {
        $dimension = $localized->dimension();
        $sources = [];
        $unlocalized = $this->repository->find($root, $dimension->unlocalizedCounterpart());

        if ($unlocalized instanceof DimensionContent) {
            $sources[] = $unlocalized;
        }

        $sources[] = $localized;

        return $this->merge($root, $dimension, $sources);
    }

    /**
     * @param non-empty-list<DimensionContent> $sources in merge order
     */
    private function merge(ContentRoot $root, DimensionKey $dimension, array $sources): DimensionContent
    {
        $target = $root->createDimensionContent($dimension);

        foreach ($sources as $source) {
            foreach ($this->mergers as $merger) {
                $merger->merge($target, $source);
            }
        }

        return $target;
    }
}

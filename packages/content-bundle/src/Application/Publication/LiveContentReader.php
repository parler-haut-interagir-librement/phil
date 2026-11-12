<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Version;

/**
 * What a reader is served: the live row of the first locale of the chain that is published, in the version
 * that locale was published from, merged over the live shared row of that version.
 *
 * The merged row's dimension names the locale served, so the caller can tell whether the chain left the
 * requested language (`FallbackChain::leavesRequestedLanguage()`).
 */
final readonly class LiveContentReader
{
    public function __construct(
        private DimensionContentRepository $rows,
        private ContentAggregator $aggregator,
    ) {
    }

    /**
     * @throws ContentNotFound when no locale of the chain is published
     */
    public function read(ContentRoot $root, FallbackChain $chain): DimensionContent
    {
        foreach ($chain->locales as $locale) {
            $version = $this->rows->latestLocalizedVersion($root, Stage::Live, $locale);

            if ($version instanceof Version) {
                return $this->aggregator->aggregate($root, DimensionKey::localized($locale, Stage::Live, $version));
            }
        }

        throw ContentNotFound::liveAlongChain($chain);
    }
}

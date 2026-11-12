<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * The dated archive of the kinds declared under `phil_content.archives` — a « now » page, a changelog, a statement
 * that changes over time —: every version a locale was published from, with its date, and what readers read then.
 *
 * A publication freezes the draft rows it published and stamps them with its date, and the next edit forks a new
 * version: the frozen rows are the archive, and nothing more is stored. A version is in the archive of a locale when
 * that locale's draft row of the version carries a publication date; it reads as it was published, its localized row
 * merged over the shared row of the version, the body in its author's format with its HTML.
 *
 * Only the declared kinds have a public archive: for any other, the history of a content is the editors' business,
 * and asking is refused.
 */
final readonly class ContentArchive
{
    /**
     * @param list<string> $resourceKeys the kinds whose archive is public
     */
    public function __construct(
        private ContentRootRepository $roots,
        private DimensionContentRepository $rows,
        private ContentAggregator $aggregator,
        private ContentNormalizer $normalizer,
        private array $resourceKeys = [],
    ) {
    }

    public function isArchived(string $resourceKey): bool
    {
        return \in_array($resourceKey, $this->resourceKeys, true);
    }

    /**
     * The publications of `$locale`, the latest first.
     *
     * @return list<ArchivedVersion>
     *
     * @throws InvalidContent  when the kind has no public archive
     * @throws ContentNotFound when the root does not exist
     */
    public function history(ContentReference $reference, Locale $locale): array
    {
        $versions = [];

        foreach ($this->rows->findVersions($this->root($reference), Stage::Draft, $locale) as $row) {
            $publishedAt = $row instanceof WorkflowCapable ? $row->getWorkflowPublished() : null;

            if ($publishedAt instanceof DateTimeImmutable) {
                $versions[] = new ArchivedVersion($row->dimension()->version->number, $publishedAt);
            }
        }

        return $versions;
    }

    /**
     * What readers of `$locale` read when version `$version` was published, normalized.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidContent  when the kind has no public archive
     * @throws ContentNotFound when the root does not exist, or the locale was never published from that version
     */
    public function read(ContentReference $reference, Locale $locale, int $version): array
    {
        $root = $this->root($reference);

        try {
            $key = DimensionKey::localized($locale, Stage::Draft, Version::of($version));
        } catch (InvalidLocaleDimension) {
            throw ContentNotFound::inStage(Stage::Draft, $locale);
        }

        $row = $this->rows->find($root, $key);

        if (!$row instanceof WorkflowCapable || !$row->getWorkflowPublished() instanceof DateTimeImmutable) {
            throw ContentNotFound::forDimension($key);
        }

        return $this->normalizer->normalize($this->aggregator->aggregate($root, $key));
    }

    /**
     * @throws InvalidContent
     * @throws ContentNotFound
     */
    private function root(ContentReference $reference): ContentRoot
    {
        if (!$this->isArchived($reference->resourceKey)) {
            throw InvalidContent::because(\sprintf('"%s" has no public archive; declare it under "phil_content.archives".', $reference->resourceKey));
        }

        return $this->roots->find($reference) ?? throw ContentNotFound::forReference($reference);
    }
}

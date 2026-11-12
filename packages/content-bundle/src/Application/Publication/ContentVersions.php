<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\LocaleDimensions\Version;

/**
 * The versions of a root's drafts: which is the latest, whether it is frozen, and the fork to the next one.
 *
 * - **Drafts are edited in the latest version.** Older versions are the record of what was published.
 * - **A version is frozen once one of its locales is published**: publishing stamps the publication date on
 *   the draft rows it copied, and a draft row with a publication date is never written again.
 * - **Editing a frozen version forks it**: every draft row of the version, shared and localized, is copied to
 *   the next version without its publication date, and the edit goes there.
 *
 * The live stage holds, for each locale, the rows of the version it was last published from (see
 * `ContentPublisher`).
 */
final readonly class ContentVersions
{
    public function __construct(
        private DimensionContentRepository $rows,
        private DimensionContentCopier $copier,
        private ContentTransaction $transaction,
    ) {
    }

    public function latestDraft(ContentRoot $root): ?Version
    {
        return $this->rows->latestVersion($root, Stage::Draft);
    }

    public function isFrozen(ContentRoot $root, Version $version): bool
    {
        return array_any($this->rows->findAll($root, Stage::Draft, $version), static fn (DimensionContent $row): bool => $row instanceof WorkflowCapable && $row->getWorkflowPublished() instanceof DateTimeImmutable);
    }

    /**
     * Copies every draft row of `$version` to the next version, flushes, and returns the copies.
     *
     * @return list<DimensionContent>
     */
    public function fork(ContentRoot $root, Version $version): array
    {
        $next = $version->next();
        $copies = [];

        foreach ($this->rows->findAll($root, Stage::Draft, $version) as $row) {
            $copy = $this->copier->copy($row, $row->dimension()->atVersion($next));

            if ($copy instanceof WorkflowCapable) {
                $copy->setWorkflowPublished(null);
            }

            $this->rows->add($copy);
            $copies[] = $copy;
        }

        $this->transaction->flush();

        return $copies;
    }
}

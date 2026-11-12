<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Editing;

use Phil\ContentBundle\Application\Pipeline\ContentDataMapper;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Application\Publication\ContentWorkflow;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Slug\Slug;

/**
 * Writes submitted data onto the draft of one locale of a root.
 *
 * The draft edited is always the latest version's. **A frozen version is never written**: it is forked to
 * the next version first (`ContentVersions::fork()`), and every localized row of the fork that was
 * `published` moves to `draft`, since the shared row it is read over may change in this version. A locale
 * without a row in the version gets one, `unpublished`; so does the shared row.
 *
 * A slug is checked against the route table before it is written: it must be free in its locale, the root's
 * own, or a history route that gives it up.
 */
final readonly class ContentEditor
{
    public function __construct(
        private DimensionContentRepository $rows,
        private ContentVersions $versions,
        private ContentWorkflow $workflow,
        private ContentDataMapper $dataMapper,
        private RouteUpdater $routes,
    ) {
    }

    /**
     * @param array<string, mixed> $data the keys of the normalized form
     *
     * @throws InvalidContent|SlugTaken|DomainError when a submitted value is refused
     */
    public function edit(ContentRoot $root, ContentReference $reference, Locale $locale, array $data): void
    {
        [$shared, $localized] = $this->draftRows($root, $locale);

        $this->dataMapper->map($shared, $localized, $data);

        $slug = $localized instanceof RoutableCapable ? $localized->getSlug() : null;

        if ($slug instanceof Slug) {
            $this->routes->assertAvailable($reference, $locale, $slug);
        }
    }

    /**
     * The shared and the localized draft rows of the latest version, forked and created as needed.
     *
     * @return array{DimensionContent, DimensionContent}
     */
    private function draftRows(ContentRoot $root, Locale $locale): array
    {
        $version = $this->versions->latestDraft($root) ?? Version::first();

        if ($this->versions->isFrozen($root, $version)) {
            foreach ($this->versions->fork($root, $version) as $copy) {
                if ($copy->dimension()->isLocalized() && $this->workflow->can($copy, ContentWorkflow::CREATE_DRAFT)) {
                    $this->workflow->apply($copy, ContentWorkflow::CREATE_DRAFT);
                }
            }

            $version = $version->next();
        }

        return [
            $this->rowOf($root, DimensionKey::unlocalized(Stage::Draft, $version)),
            $this->rowOf($root, DimensionKey::localized($locale, Stage::Draft, $version)),
        ];
    }

    private function rowOf(ContentRoot $root, DimensionKey $key): DimensionContent
    {
        $row = $this->rows->find($root, $key);

        if ($row instanceof DimensionContent) {
            return $row;
        }

        $row = $root->createDimensionContent($key);

        if ($key->isLocalized() && $row instanceof WorkflowCapable) {
            $row->setWorkflowPlace(ContentWorkflow::UNPUBLISHED);
        }

        $this->rows->add($row);

        return $row;
    }
}

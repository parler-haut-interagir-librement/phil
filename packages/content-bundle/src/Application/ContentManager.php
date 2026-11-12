<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application;

use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\Editing\ContentEditor;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Publication\PageEvents;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * The PHP interface of the bundle: what the console commands call, and what a Doctrine migration of the
 * application calls to write content.
 *
 * ```php
 * $news = $contents->create('news', 'fr', [
 *     'templateKey' => 'news',
 *     'slug' => 'ouverture-du-site',
 *     'excerptTitle' => 'Ouverture du site',
 *     'bodySource' => "Le site **ouvre** aujourd'hui.",
 * ]);
 * $contents->publish($news, 'fr');
 * ```
 *
 * Every writing method runs in its own `ContentTransaction`, nested in the caller's when one is open, and
 * refuses a locale that `phil_content.locales` does not serve. Data uses the keys of the normalized form
 * (README, "Submitted data"); an absent key changes nothing, `null` clears.
 */
final readonly class ContentManager
{
    public function __construct(
        private ContentRootRepository $roots,
        private ContentRootFactory $factory,
        private DimensionContentRepository $rows,
        private ContentEditor $editor,
        private ContentPublisher $publisher,
        private ContentVersions $versions,
        private LiveContentReader $reader,
        private ContentAggregator $aggregator,
        private ContentNormalizer $normalizer,
        private DeclaredLocales $locales,
        private ContentTransaction $transaction,
        private RouteRepository $routes,
        private ?PageEvents $pages = null,
        private ?SingleKinds $singles = null,
        private ?WorkingCopyRepository $workingCopies = null,
    ) {
    }

    /**
     * Creates a root of `$resourceKey` and its first draft in `$locale`, version 1, `unpublished`. A single kind
     * (`phil_content.singles`) is created once: its second root is refused.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainError when the locale is not served, a value is refused, or the single kind already has its root
     */
    public function create(string $resourceKey, Locale|string $locale, array $data = []): ContentReference
    {
        $locale = $this->served($locale);

        if (true === $this->singles?->isSingle($resourceKey) && $this->single($resourceKey) instanceof ContentReference) {
            throw InvalidContent::because(\sprintf('"%s" is a single kind and already has its content: edit it instead.', $resourceKey));
        }

        return $this->transaction->run(function () use ($resourceKey, $locale, $data): ContentReference {
            $root = $this->factory->create($resourceKey);
            $this->roots->add($root);
            // A root whose identifier the database generates has one once flushed.
            $this->transaction->flush();
            $reference = $this->roots->referenceOf($root);

            $this->editor->edit($root, $reference, $locale, $data);

            return $reference;
        });
    }

    /**
     * Edits the draft of `$locale`, forking a frozen version first.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainError
     */
    public function edit(ContentReference $reference, Locale|string $locale, array $data): void
    {
        $locale = $this->served($locale);

        $this->transaction->run(function () use ($reference, $locale, $data): void {
            $this->editor->edit($this->root($reference), $reference, $locale, $data);
        });
    }

    /**
     * Publishes the latest draft of `$locale`, then dispatches `PagePublished` when the content has an address there.
     * A draft whose body loses part of itself in UJO is refused unless `$acceptLosses`.
     *
     * @throws DomainError
     */
    public function publish(ContentReference $reference, Locale|string $locale, bool $acceptLosses = false): void
    {
        $locale = $this->served($locale);

        $this->transaction->run(function () use ($reference, $locale, $acceptLosses): void {
            $this->publisher->publish($this->root($reference), $locale, $acceptLosses);
        });

        $this->pages?->dispatch($this->pages->published($this->root($reference), $reference, $locale));
    }

    /**
     * Takes `$locale` offline, then dispatches `PageRetired` when the content had an address there.
     *
     * @throws DomainError
     */
    public function unpublish(ContentReference $reference, Locale|string $locale): void
    {
        $locale = $this->served($locale);
        $retired = $this->pages?->retired($this->root($reference), $reference, $locale);

        $this->transaction->run(function () use ($reference, $locale): void {
            $this->publisher->unpublish($this->root($reference), $locale);
        });

        $this->pages?->dispatch($retired);
    }

    /**
     * @throws DomainError
     */
    public function removeDraft(ContentReference $reference, Locale|string $locale): void
    {
        $locale = $this->served($locale);

        $this->transaction->run(function () use ($reference, $locale): void {
            $this->publisher->removeDraft($this->root($reference), $locale);
        });
    }

    /**
     * Deletes a root, every row of it in both stages and every version, its working copies, and every route of it,
     * current and history: its addresses are free again, for another root of the same kind. Each locale that was live dispatches
     * `PageRetired` once the root is gone.
     *
     * @throws DomainError
     */
    public function delete(ContentReference $reference): void
    {
        $retired = $this->pages?->retiredEverywhere($this->root($reference), $reference, $this->locales->all()) ?? [];

        $this->transaction->run(function () use ($reference): void {
            $root = $this->root($reference);

            foreach ([Stage::Live, Stage::Draft] as $stage) {
                $latest = $this->rows->latestVersion($root, $stage);

                for ($version = Version::first(); $latest instanceof Version && !$version->isAfter($latest); $version = $version->next()) {
                    foreach ($this->rows->findAll($root, $stage, $version) as $row) {
                        $this->rows->remove($row);
                    }
                }
            }

            foreach ($this->routes->findAllOf($reference) as $route) {
                $this->routes->remove($route);
            }

            if ($this->workingCopies instanceof WorkingCopyRepository) {
                foreach ($this->workingCopies->findAllOf($reference) as $copy) {
                    $this->workingCopies->remove($copy);
                }
            }

            // One flush, at the end of the transaction: the rows, which point to the root, go first.
            $this->roots->remove($root);
        });

        $this->pages?->dispatch(...$retired);
    }

    /**
     * The latest draft of `$locale`, merged over its shared row and normalized.
     *
     * @return array<string, mixed>
     *
     * @throws DomainError
     */
    public function draft(ContentReference $reference, Locale|string $locale): array
    {
        $locale = $this->served($locale);
        $root = $this->root($reference);
        $version = $this->versions->latestDraft($root) ?? throw ContentNotFound::inStage(Stage::Draft, $locale);

        return $this->normalizer->normalize($this->aggregator->aggregate($root, DimensionKey::localized($locale, Stage::Draft, $version)));
    }

    /**
     * What a reader of `$locale` is served, along its declared fallback chain, normalized.
     *
     * @return array<string, mixed>
     *
     * @throws DomainError
     */
    public function live(ContentReference $reference, Locale|string $locale): array
    {
        $chain = DimensionLocales::chain($this->locales, $this->served($locale));

        return $this->normalizer->normalize($this->reader->read($this->root($reference), $chain));
    }

    /**
     * @return list<ContentReference>
     */
    public function list(string $resourceKey): array
    {
        return array_map($this->roots->referenceOf(...), $this->roots->findAll($resourceKey));
    }

    /**
     * The root of a single kind, or `null` before it is created.
     *
     * @throws InvalidContent when the kind is not declared single
     */
    public function single(string $resourceKey): ?ContentReference
    {
        if (true !== $this->singles?->isSingle($resourceKey)) {
            throw InvalidContent::because(\sprintf('"%s" is not a single kind; declare it under "phil_content.singles".', $resourceKey));
        }

        $roots = $this->roots->findAll($resourceKey);

        return [] === $roots ? null : $this->roots->referenceOf($roots[0]);
    }

    /**
     * One status per locale of the latest draft version, ordered by locale.
     *
     * @return list<ContentStatus>
     *
     * @throws DomainError
     */
    public function status(ContentReference $reference): array
    {
        $root = $this->root($reference);
        $version = $this->versions->latestDraft($root);

        if (!$version instanceof Version) {
            return [];
        }

        $statuses = [];

        foreach ($this->rows->findAll($root, Stage::Draft, $version) as $row) {
            $locale = $row->dimension()->locale;

            if (!$locale instanceof Locale) {
                continue;
            }

            $statuses[] = new ContentStatus(
                $reference,
                $locale->value,
                $version->number,
                $row instanceof WorkflowCapable ? $row->getWorkflowPlace() : null,
                $row instanceof RoutableCapable ? $row->getSlug()?->value : null,
                $this->rows->latestLocalizedVersion($root, Stage::Live, $locale)?->number,
            );
        }

        return $statuses;
    }

    /**
     * A locale `phil_content.locales` serves, read from its string.
     *
     * @throws InvalidDimension when the locale is malformed or not served
     */
    public function servedLocale(Locale|string $locale): Locale
    {
        return $this->served($locale);
    }

    /**
     * Whether the root exists.
     */
    public function exists(ContentReference $reference): bool
    {
        return $this->roots->find($reference) instanceof ContentRoot;
    }

    /**
     * @throws ContentNotFound
     */
    private function root(ContentReference $reference): ContentRoot
    {
        return $this->roots->find($reference) ?? throw ContentNotFound::forReference($reference);
    }

    /**
     * @throws InvalidDimension when the locale is malformed or not served
     */
    private function served(Locale|string $locale): Locale
    {
        $locale = \is_string($locale) ? DimensionLocales::parse($locale) : $locale;
        DimensionLocales::chain($this->locales, $locale);

        return $locale;
    }
}

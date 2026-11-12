<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Application\Routing\RouteMatch;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\PublishedOrder;
use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Domain\ValueObject\PublishedRowQuery;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DirectoryBundle\Application\Capability\RelatedPipeline;
use Phil\DirectoryBundle\Domain\Capability\EditedByCapable;
use Phil\DirectoryBundle\Domain\Capability\RelatedCapable;
use Phil\DirectoryBundle\Domain\Exception\ListingRefused;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\Reader;
use Phil\LocaleDimensions\DeclaredLocales;

/**
 * The directory: listings written by their author and editors, each change a version of the content that holds it.
 * An administrator's change is published at once; another's waits as a draft for an administrator to publish it,
 * unless the directory publishes every change.
 *
 * Listings are related by the relations of the configuration: written on the listing of the relation's first genre,
 * checked here — the relation is the genre's, its listings exist and are of the second genre —, shown on both.
 *
 * Each read of published listings asks for a reader of phil/indexing-consent: the list of a genre and the related
 * listings are the site's own listings, the sitemap is the sitemap; a listing its author withdrew is left out where
 * its choice says.
 */
final readonly class Directory
{
    public const string RESOURCE_KEY = 'listing';

    public function __construct(
        private ContentManager $contents,
        private ContentRootRepository $roots,
        private DimensionContentRepository $rows,
        private ContentTransaction $transaction,
        private PublishedContents $published,
        private RouteResolver $routes,
        private DeclaredLocales $locales,
        private Genres $genres,
        private UserErasure $erasure,
        private RelatedLookup $relatedLookup,
        private bool $publishEveryChange = false,
        private Relations $relations = new Relations(),
    ) {
    }

    /**
     * @param array<string, mixed> $data the keys of content-bundle and of the directory's capabilities
     */
    public function create(string $genre, string $locale, array $data, Actor $actor): ContentReference
    {
        $genre = $this->genres->get($genre)->key;
        $user = $actor->user ?? throw ListingRefused::because('Only someone signed in writes a listing.');

        $data = $this->checkedRelated($genre, null, $data);

        return $this->transaction->run(function () use ($genre, $locale, $data, $actor, $user): ContentReference {
            $reference = $this->contents->create(self::RESOURCE_KEY, $locale, ['editedBy' => $user] + $data);
            $this->listing($reference)->assign($genre, $user);
            $this->transaction->flush();
            $this->publishFor($reference, $locale, $actor);

            return $reference;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function edit(ContentReference $reference, string $locale, array $data, Actor $actor): void
    {
        $listing = $this->listing($reference);

        if (!$this->mayEdit($listing, $actor)) {
            throw ListingRefused::because('Only the author of a listing, its editors and the administrators change it.');
        }

        $data = $this->checkedRelated((string) $listing->genre(), $reference, $data);

        $this->transaction->run(function () use ($reference, $locale, $data, $actor): void {
            $this->contents->edit($reference, $locale, ['editedBy' => $actor->user] + $data);
            $this->publishFor($reference, $locale, $actor);
        });
    }

    public function publish(ContentReference $reference, string $locale, Actor $actor): void
    {
        $this->listing($reference);

        if (!$actor->admin) {
            throw ListingRefused::because('Only an administrator publishes a listing.');
        }

        $this->contents->publish($reference, $locale);
    }

    /**
     * Changes who finds a listing — listed, kept out of search engines, or reachable only by its address —, at once:
     * the choice of its author, or of an administrator.
     */
    public function changeIndexing(ContentReference $reference, Indexing $indexing, Actor $actor): void
    {
        $listing = $this->listing($reference);

        if (!$this->mayManageEditors($listing, $actor)) {
            throw ListingRefused::because('Only the author of a listing and the administrators decide who finds it.');
        }

        $this->transaction->run(function () use ($listing, $indexing): void {
            $listing->changeIndexing($indexing);
            $this->transaction->flush();
        });
    }

    public function addEditor(ContentReference $reference, string $editor, Actor $actor): void
    {
        $this->changeEditors($reference, $actor, static fn (Listing $listing) => $listing->addEditor($editor));
    }

    public function removeEditor(ContentReference $reference, string $editor, Actor $actor): void
    {
        $this->changeEditors($reference, $actor, static fn (Listing $listing) => $listing->removeEditor($editor));
    }

    /**
     * Erases a user from the directory, when its account is erased: its identifier leaves the listings it wrote or
     * edits and every version it wrote. The listings and their versions stay; a listing it wrote has no author left,
     * and the administrators name its editors. What the application calls from its own erasure of an account.
     *
     * @return int how many listings and versions changed
     */
    public function eraseUser(string $user): int
    {
        return $this->erasure->erase($user);
    }

    /**
     * The latest draft of a listing in a locale, normalized: what its editors change.
     *
     * @return array<string, mixed>
     */
    public function draft(ContentReference $reference, string $locale): array
    {
        $this->listing($reference);

        return $this->contents->draft($reference, $locale);
    }

    public function mayEdit(Listing $listing, Actor $actor): bool
    {
        return $actor->admin || $listing->mayEdit($actor->user);
    }

    public function mayManageEditors(Listing $listing, Actor $actor): bool
    {
        return $actor->admin || $listing->isAuthor($actor->user);
    }

    /**
     * The listing of a reference, as the store holds it now: who may change it and who finds it change between reads.
     *
     * @phpstan-impure
     */
    public function listing(ContentReference $reference): Listing
    {
        $listing = self::RESOURCE_KEY === $reference->resourceKey ? $this->roots->find($reference) : null;

        return $listing instanceof Listing ? $listing : throw ListingRefused::because(\sprintf('The directory has no listing %s.', $reference->toString()));
    }

    /**
     * The listings of a genre readers of a locale are served, in the order of their slugs, maybe a page of them: one
     * read of the published rows, whatever their number. A listing without a slug has no page, and is left out; so is
     * a listing whose author does not let the reader use it — the site's own listings by default.
     *
     * @return list<PublishedContent>
     */
    public function published(string $genre, string $locale, int $offset = 0, ?int $limit = null, Reader $reader = Reader::SiteListings): array
    {
        return $this->published->find(new PublishedRowQuery(
            self::RESOURCE_KEY,
            DimensionLocales::parse($locale),
            PublishedOrder::Slug,
            ['genre' => $this->genres->get($genre)->key] + $this->reach($reader),
            withSlug: true,
            offset: $offset,
            limit: $limit,
        ));
    }

    /**
     * The listing of a genre at a slug, along the fallback chain of the locale: what readers are served, or the slug
     * an old one moved to; null when the genre has none there.
     */
    public function find(string $genre, string $slug, string $locale): ?ListingPage
    {
        $match = $this->routes->resolve(self::RESOURCE_KEY, DimensionLocales::chain($this->locales, DimensionLocales::parse($locale)), $slug);
        $listing = $match instanceof RouteMatch ? $this->listing($match->route->content()) : null;

        if (!$match instanceof RouteMatch || !$listing instanceof Listing || $listing->genre() !== $this->genres->get($genre)->key) {
            return null;
        }

        if ($match->redirect) {
            return new ListingPage($match->route->content(), $listing, movedTo: $match->route->slug()->value);
        }

        return new ListingPage($match->route->content(), $listing, $this->contents->live($match->route->content(), $locale));
    }

    /**
     * The published listings related to one, in a locale: first those it points at, by the relations of its genre, then
     * those that point at it; a relation with none is left out.
     *
     * The listing's own relations are read as its page is served, along the locale's fallback chain; the related
     * listings are those published in the locale, as the list of a genre shows them, read by their identifiers. Those
     * that point at it are the listings of the other genre whose live rows name it, found by `RelatedLookup`, then
     * read by their identifiers and kept when their published row names it: the cost follows the listings related,
     * not the size of the genre.
     *
     * @return list<RelatedListings>
     */
    public function related(ContentReference $reference, string $locale): array
    {
        $genre = (string) $this->listing($reference)->genre();
        $related = [];
        $own = $this->liveRelated($reference, $locale);

        foreach ($this->relations->from($genre) as $relation) {
            $ids = $own[$relation->key] ?? [];
            $listings = [] === $ids ? [] : $this->published->find(new PublishedRowQuery(
                self::RESOURCE_KEY,
                DimensionLocales::parse($locale),
                PublishedOrder::Slug,
                ['genre' => $relation->to] + $this->reach(Reader::SiteListings),
                $ids,
                withSlug: true,
            ));
            $related[] = new RelatedListings($relation, false, $this->genres->get($relation->to), $listings);
        }

        foreach ($this->relations->to($genre) as $relation) {
            $ids = $this->relatedLookup->pointingAt($relation->from, $relation->key, $reference->resourceId);
            $candidates = [] === $ids ? [] : $this->published->find(new PublishedRowQuery(
                self::RESOURCE_KEY,
                DimensionLocales::parse($locale),
                PublishedOrder::Slug,
                ['genre' => $relation->from] + $this->reach(Reader::SiteListings),
                $ids,
                withSlug: true,
            ));
            // The published row decides: a draft that names the listing is not shown.
            $listings = array_values(array_filter($candidates, static fn (PublishedContent $content): bool => $content->content instanceof RelatedCapable && \in_array($reference->resourceId, $content->content->getRelated()[$relation->key] ?? [], true)));
            $related[] = new RelatedListings($relation, true, $this->genres->get($relation->from), $listings);
        }

        return array_values(array_filter($related, static fn (RelatedListings $group): bool => [] !== $group->listings));
    }

    /**
     * The fields of a listing a query keeps for a reader: its choice when only listed listings are allowed, else
     * whether the site lists it.
     *
     * @return array<string, bool|string>
     */
    private function reach(Reader $reader): array
    {
        return [Indexing::Listed] === Indexing::allowing($reader) ? ['indexing' => Indexing::Listed->value] : ['siteListed' => true];
    }

    /**
     * The relations written on the listings of a genre, for their form.
     *
     * @return list<Relation>
     */
    public function relationsFrom(string $genre): array
    {
        return $this->relations->from($this->genres->get($genre)->key);
    }

    /**
     * Every version of a listing in a locale, the latest first.
     *
     * @return list<ListingVersion>
     */
    public function history(ContentReference $reference, string $locale): array
    {
        $versions = [];

        foreach ($this->rows->findVersions($this->listing($reference), Stage::Draft, DimensionLocales::parse($locale)) as $row) {
            $versions[] = new ListingVersion(
                $row->dimension()->version->number,
                $row instanceof ExcerptCapable ? $row->getExcerptTitle() : null,
                $row instanceof EditedByCapable ? $row->getEditedBy() : null,
                $row instanceof WorkflowCapable ? $row->getWorkflowPublished() : null,
            );
        }

        return $versions;
    }

    /**
     * The data with its related listings checked against the relations of the genre: each relation written on the
     * genre's listings, each listing there and of the relation's other genre, never the listing itself, one at most
     * unless the relation takes several. A relation the configuration does not declare is dropped.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws ListingRefused
     */
    private function checkedRelated(string $genre, ?ContentReference $self, array $data): array
    {
        if (!\array_key_exists('related', $data) || null === $data['related']) {
            return $data;
        }

        $related = RelatedPipeline::shaped(\is_array($data['related']) ? $data['related'] : throw ListingRefused::because('The related listings are lists of identifiers by relation.'));

        foreach ($related as $key => $ids) {
            if (!$this->relations->has($key)) {
                // A relation the configuration no longer declares is dropped, as a removed genre is hidden.
                unset($related[$key]);

                continue;
            }

            $relation = $this->relations->get($key);

            if ($relation->from !== $genre) {
                throw ListingRefused::because(\sprintf('The relation "%s" is not written on a listing of the genre "%s".', $key, $genre));
            }

            if (!$relation->multiple && \count($ids) > 1) {
                throw ListingRefused::because(\sprintf('The relation "%s" relates one listing at most.', $key));
            }

            foreach ($ids as $id) {
                $this->checkTarget($relation, $self, $id);
            }
        }

        return ['related' => $related] + $data;
    }

    /**
     * @throws ListingRefused
     */
    private function checkTarget(Relation $relation, ?ContentReference $self, string $id): void
    {
        if ($self?->resourceId === $id) {
            throw ListingRefused::because('A listing is not related to itself.');
        }

        try {
            $target = $this->listing(ContentReference::of(self::RESOURCE_KEY, $id));
        } catch (DomainError) {
            throw ListingRefused::because(\sprintf('The directory has no listing %s.', $id));
        }

        if ($target->genre() !== $relation->to) {
            throw ListingRefused::because(\sprintf('The relation "%s" relates listings of the genre "%s".', $relation->key, $relation->to));
        }
    }

    /**
     * The related listings of a listing as its page is served in a locale; none when it is not served there.
     *
     * @return array<string, list<string>>
     */
    private function liveRelated(ContentReference $reference, string $locale): array
    {
        try {
            $related = $this->contents->live($reference, $locale)['related'] ?? [];
        } catch (DomainError) {
            return [];
        }

        return \is_array($related) ? RelatedPipeline::shaped($related) : [];
    }

    /**
     * @param callable(Listing): void $change
     */
    private function changeEditors(ContentReference $reference, Actor $actor, callable $change): void
    {
        $listing = $this->listing($reference);

        if (!$this->mayManageEditors($listing, $actor)) {
            throw ListingRefused::because('Only the author of a listing and the administrators name its editors.');
        }

        $this->transaction->run(function () use ($listing, $change): void {
            $change($listing);
            $this->transaction->flush();
        });
    }

    private function publishFor(ContentReference $reference, string $locale, Actor $actor): void
    {
        if ($actor->admin || $this->publishEveryChange) {
            $this->contents->publish($reference, $locale);
        }
    }
}

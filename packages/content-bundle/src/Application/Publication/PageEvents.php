<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Application\Contract\CanonicalAddresses;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\Url\Url;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The `PagePublished` and `PageRetired` events of `phil/publishing`, read from the live row of one locale: its address,
 * the canonical URL of its SEO when it has one, else the address of its current route; « indexable » unless its SEO
 * says `noindex`; the language tag of the locale; the reference of the content as the owner identifier.
 *
 * The events are built from what readers see, so a retirement is read **before** the change, while the live row is
 * still there, and every event is dispatched **after** the transaction that made the change returned. A locale that
 * is not live, or has no address, gives no event; without an event dispatcher, or without `CanonicalAddresses`,
 * nothing is built.
 */
final readonly class PageEvents
{
    public function __construct(
        private DimensionContentRepository $rows,
        private ContentAggregator $aggregator,
        private ?CanonicalAddresses $addresses = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function published(ContentRoot $root, ContentReference $reference, Locale $locale): ?PagePublished
    {
        $page = $this->page($root, $reference, $locale);

        return null === $page ? null : new PagePublished($page[0], $locale->toLanguageTag(), $page[1], $reference->toString());
    }

    public function retired(ContentRoot $root, ContentReference $reference, Locale $locale): ?PageRetired
    {
        $page = $this->page($root, $reference, $locale);

        return null === $page ? null : new PageRetired($page[0], $locale->toLanguageTag(), $page[1], $reference->toString());
    }

    /**
     * The retirement of every locale the root is live in, in the order of `$locales`.
     *
     * @param iterable<Locale> $locales
     *
     * @return list<PageRetired>
     */
    public function retiredEverywhere(ContentRoot $root, ContentReference $reference, iterable $locales): array
    {
        $events = [];

        foreach ($locales as $locale) {
            $event = $this->retired($root, $reference, $locale);

            if ($event instanceof PageRetired) {
                $events[] = $event;
            }
        }

        return $events;
    }

    public function dispatch(PagePublished|PageRetired|null ...$events): void
    {
        foreach ($events as $event) {
            if (null !== $event) {
                $this->dispatcher?->dispatch($event);
            }
        }
    }

    /**
     * The address and the « indexable » flag of the live row of the locale.
     *
     * @return array{Url, bool}|null
     */
    private function page(ContentRoot $root, ContentReference $reference, Locale $locale): ?array
    {
        if (!$this->dispatcher instanceof EventDispatcherInterface || !$this->addresses instanceof CanonicalAddresses) {
            return null;
        }

        $version = $this->rows->latestLocalizedVersion($root, Stage::Live, $locale);

        if (!$version instanceof Version) {
            return null;
        }

        $live = $this->aggregator->aggregate($root, DimensionKey::localized($locale, Stage::Live, $version));
        $canonical = $this->canonical($live) ?? $this->addresses->of($reference, $locale);

        return $canonical instanceof Url ? [$canonical, !$live instanceof SeoCapable || true !== $live->getSeoNoIndex()] : null;
    }

    private function canonical(DimensionContent $live): ?Url
    {
        return $live instanceof SeoCapable ? $live->getSeoCanonicalUrl() : null;
    }
}

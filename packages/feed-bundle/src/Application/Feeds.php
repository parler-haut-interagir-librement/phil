<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application;

use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Application\Exception\InvalidSource;
use Phil\FeedBundle\Application\Exception\UnknownFeed;
use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;

/**
 * The feeds of the application, by name: each source, its feed for a page, and the feeds a page that went online or
 * offline belongs to.
 *
 * The sources are indexed by name the first time one is asked for, then kept: they are services, the same for every
 * request, and nothing of a request is kept.
 */
final class Feeds
{
    public const string NAME_PATTERN = '[a-z0-9][a-z0-9_-]{0,63}';

    /** @var array<string, FeedSource>|null */
    private ?array $byName = null;

    /**
     * @param iterable<FeedSource> $sources
     * @param int                  $limit   the entries of a page, 1 to 500
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly int $limit = 20,
    ) {
    }

    /**
     * @return list<string> in the order of the sources
     */
    public function names(): array
    {
        return array_keys($this->index());
    }

    /**
     * @throws UnknownFeed
     */
    public function source(string $name): FeedSource
    {
        return $this->index()[$name] ?? throw UnknownFeed::named($name);
    }

    /**
     * The feed of a source for one page: at most the limit of entries, and whether an older page follows.
     *
     * @throws UnknownFeed when no source has that name
     * @throws InvalidFeed when the page is not 1 to 1000, or the source gives a part no format could write
     */
    public function feed(string $name, int $page = 1): Feed
    {
        $source = $this->source($name);
        $query = new FeedQuery($this->limit, $page);
        $answer = $source->items($query);
        $items = $answer->items;

        return new Feed($source->channel(), \array_slice($items, 0, $query->limit), $page, $answer->hasNextPage || \count($items) > $query->limit);
    }

    /**
     * The names of the feeds that list, or listed, the page.
     *
     * @return list<string>
     */
    public function including(PageEvent $page): array
    {
        $names = [];

        foreach ($this->index() as $name => $source) {
            if ($source->includes($page)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array<string, FeedSource>
     */
    private function index(): array
    {
        if (null !== $this->byName) {
            return $this->byName;
        }

        $byName = [];

        foreach ($this->sources as $source) {
            $name = $source->name();

            if (1 !== preg_match('/^'.self::NAME_PATTERN.'$/D', $name)) {
                throw InvalidSource::name($name, $source::class);
            }

            if (isset($byName[$name])) {
                throw InvalidSource::duplicate($name, $source::class, $byName[$name]::class);
            }

            $byName[$name] = $source;
        }

        return $this->byName = $byName;
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceCatalogBundle\Application\Contract\ProductListing;
use Phil\ProductFeeds\FeedPublisher;
use Phil\ProductFeeds\PublishedFeed;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * The feeds the configuration declares, each published on demand: its catalogue read through `ProductListing` and
 * written by `FeedPublisher`, whole or not at all. The command, the message handler and an application's own code
 * publish through it.
 *
 * The moment the price books are read at is the clock's, or the system's without one. It keeps nothing between two feeds: one instance serves every message of a worker.
 */
final readonly class ProductFeeds
{
    /** @var array<string, FeedDefinition> */
    private array $definitions;

    /**
     * @param iterable<FeedDefinition> $definitions
     */
    public function __construct(
        iterable $definitions,
        private ProductListing $listing,
        private ?ClockInterface $clock = null,
        private FeedPublisher $publisher = new FeedPublisher(),
        private ?UnitPriceResolver $prices = null,
        private ?LoggerInterface $logger = null,
    ) {
        $byName = [];

        foreach ($definitions as $definition) {
            $byName[$definition->name] = $definition;
        }

        $this->definitions = $byName;
    }

    /**
     * @return list<string> the names of the feeds, in the order of the configuration
     */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * The feed the configuration declares under that name, or `null`.
     */
    public function definition(string $name): ?FeedDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * @throws InvalidArgumentException for a feed the configuration does not declare
     */
    public function publish(string $name): PublishedFeed
    {
        $definition = $this->definition($name) ?? throw new InvalidArgumentException(\sprintf('No feed is named "%s"; the feeds are: %s.', $name, [] === $this->definitions ? 'none' : implode(', ', $this->names())));
        $source = new CatalogProductSource($this->listing, $definition, $this->clock?->now() ?? new DateTimeImmutable(), $this->prices, $this->logger);
        $feed = $this->publisher->publish($source, $definition->channel, $definition->format->serializer(), $definition->path);
        $this->logger?->info('The feed {feed} is published: {items} items, {bytes} bytes.', ['feed' => $name, 'items' => $feed->items, 'bytes' => $feed->bytes]);

        return $feed;
    }
}

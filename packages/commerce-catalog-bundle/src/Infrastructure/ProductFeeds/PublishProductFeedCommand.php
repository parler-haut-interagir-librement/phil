<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

use InvalidArgumentException;
use Phil\ProductFeeds\Exception\ProductFeedException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Publishes the feeds the configuration declares, or those named, now: what a deployment or a cron job runs.
 */
#[AsCommand(name: self::NAME, description: 'Publishes the product feeds, or those named')]
final readonly class PublishProductFeedCommand
{
    public const string NAME = 'phil:commerce-catalog:publish-feeds';

    public function __construct(
        private ProductFeeds $feeds,
    ) {
    }

    /**
     * @param list<string> $feeds
     */
    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The feeds to publish; every feed when none is named')] array $feeds = []): int
    {
        $names = [] === $feeds ? $this->feeds->names() : $feeds;

        if ([] === $names) {
            $io->warning('No feed is declared under phil_commerce_catalog.product_feeds.');
        }

        $status = Command::SUCCESS;

        foreach ($names as $name) {
            try {
                $feed = $this->feeds->publish($name);
                $io->success(\sprintf('%s: %d items, %d bytes, in %s.', $name, $feed->items, $feed->bytes, $feed->path));
            } catch (ProductFeedException|InvalidArgumentException $exception) {
                $io->error(\sprintf('%s: %s', $name, $exception->getMessage()));
                $status = Command::FAILURE;
            }
        }

        return $status;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use InvalidArgumentException;
use Phil\ProductFeeds\Contract\FeedSerializer;
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\ProductFeeds\Exception\FeedNotWritten;
use SplFileObject;
use Throwable;

/**
 * Writes a feed file whole, or not at all: the items of a source, one at a time, into a temporary file beside the
 * feed, then renamed over it. A reader fetching the feed while it is written gets the previous file, complete; a
 * source or a disk that fails leaves the previous file in place and no temporary one behind.
 *
 * Memory stays constant whatever the length of the catalogue: each item is written as the source gives it, and only
 * the counts are kept. A source that gives more items than the publisher's maximum, a million by default, is refused,
 * so that a source that never ends does not fill the disk.
 *
 * The publisher keeps nothing between two feeds: one instance serves every request or message of a worker.
 */
final readonly class FeedPublisher
{
    public const int DEFAULT_MAX_ITEMS = 1_000_000;

    public function __construct(
        private int $maxItems = self::DEFAULT_MAX_ITEMS,
    ) {
        if ($maxItems < 1) {
            throw new InvalidArgumentException(\sprintf('A feed holds at least one item; %d is no maximum.', $maxItems));
        }
    }

    /**
     * @param string $path where the feed file is served from, in an existing, writable directory
     *
     * @throws FeedNotWritten when the directory, the disk or the source fails, or the source gives too many items
     */
    public function publish(ProductSource $source, FeedChannel $channel, FeedSerializer $serializer, string $path): PublishedFeed
    {
        $directory = \dirname($path);

        if (!is_dir($directory) || !is_writable($directory)) {
            throw FeedNotWritten::directory($directory);
        }

        $temporary = @tempnam($directory, '.'.basename($path).'.');

        // `tempnam()` falls back to the system's temporary directory, from which a rename may not be atomic.
        if (false === $temporary || \dirname($temporary) !== realpath($directory)) {
            if (false !== $temporary) {
                @unlink($temporary);
            }

            throw FeedNotWritten::temporary($directory);
        }

        try {
            [$items, $bytes] = $this->write($temporary, $source, $channel, $serializer);
            @chmod($temporary, 0o644);

            if (!@rename($temporary, $path)) {
                throw FeedNotWritten::rename($temporary, $path);
            }
        } catch (Throwable $throwable) {
            @unlink($temporary);

            throw $throwable;
        }

        return new PublishedFeed($path, $items, $bytes);
    }

    /**
     * Writes the feed to the temporary file, closed when the method returns or throws.
     *
     * @return array{int, int} the items and the bytes written
     */
    private function write(string $temporary, ProductSource $source, FeedChannel $channel, FeedSerializer $serializer): array
    {
        $file = new SplFileObject($temporary, 'w');
        $bytes = $this->put($file, $serializer->header($channel), $temporary);
        $items = 0;

        foreach ($source->items() as $item) {
            if (++$items > $this->maxItems) {
                throw FeedNotWritten::tooManyItems($this->maxItems);
            }

            $bytes += $this->put($file, $serializer->item($item), $temporary);
        }

        $bytes += $this->put($file, $serializer->footer(), $temporary);

        if (!$file->fflush()) {
            throw FeedNotWritten::write($temporary);
        }

        return [$items, $bytes];
    }

    private function put(SplFileObject $file, string $text, string $path): int
    {
        $written = $file->fwrite($text);

        if (false === $written || \strlen($text) !== $written) {
            throw FeedNotWritten::write($path);
        }

        return $written;
    }
}

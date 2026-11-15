<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use Generator;
use InvalidArgumentException;
use LogicException;
use Override;
use Phil\ProductFeeds\Contract\FeedSerializer;
use Phil\ProductFeeds\Exception\FeedNotWritten;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\FeedPublisher;
use Phil\ProductFeeds\PublishedFeed;
use Phil\ProductFeeds\Serializer\RssSerializer;
use Phil\ProductFeeds\Serializer\TsvSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedPublisher::class)]
#[CoversClass(PublishedFeed::class)]
#[CoversClass(FeedNotWritten::class)]
#[CoversClass(FeedFormat::class)]
final class FeedPublisherTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $directory = sys_get_temp_dir().'/phil-product-feeds-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directory = (string) realpath($directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->files() as $file) {
            $file = $this->directory.'/'.$file;

            if (is_file($file)) {
                unlink($file);
            }
        }

        chmod($this->directory, 0o755);
        rmdir($this->directory);
    }

    public function testTheFeedIsWrittenWholeAndCounted(): void
    {
        $path = $this->directory.'/google.xml';
        $feed = new FeedPublisher()->publish(Items::source([Items::item('A'), Items::item('B')]), Items::channel(), FeedFormat::Xml->serializer(), $path);
        $serializer = new RssSerializer();
        $expected = $serializer->header(Items::channel()).$serializer->item(Items::item('A')).$serializer->item(Items::item('B')).$serializer->footer();

        self::assertSame($expected, file_get_contents($path));
        self::assertSame([$path, 2, \strlen($expected)], [$feed->path, $feed->items, $feed->bytes]);
        self::assertSame(0o644, fileperms($path) & 0o777);
        self::assertSame(['google.xml'], $this->files());
    }

    public function testAnEmptyCatalogueIsAFeedOfNoItem(): void
    {
        $path = $this->directory.'/feed.tsv';
        $feed = new FeedPublisher()->publish(Items::source([]), Items::channel(), FeedFormat::Tsv->serializer(), $path);
        $header = new TsvSerializer()->header(Items::channel());

        self::assertSame($header, file_get_contents($path));
        self::assertSame([0, \strlen($header)], [$feed->items, $feed->bytes]);
    }

    public function testTheNewFeedReplacesTheOldOne(): void
    {
        $path = $this->directory.'/feed.tsv';
        file_put_contents($path, 'old');

        new FeedPublisher()->publish(Items::source([Items::item()]), Items::channel(), new TsvSerializer(), $path);

        self::assertStringStartsWith("id\ttitle", (string) file_get_contents($path));
        self::assertSame(['feed.tsv'], $this->files());
    }

    public function testASourceThatFailsLeavesTheOldFeedAndNoTemporaryFile(): void
    {
        $path = $this->directory.'/feed.xml';
        file_put_contents($path, 'old');
        $source = Items::source((static function (): Generator {
            yield Items::item('A');

            throw new LogicException('The catalogue is gone.');
        })());

        try {
            new FeedPublisher()->publish($source, Items::channel(), new RssSerializer(), $path);
            self::fail('The feed was written.');
        } catch (LogicException $logicException) {
            self::assertSame('The catalogue is gone.', $logicException->getMessage());
        }

        self::assertSame('old', file_get_contents($path));
        self::assertSame(['feed.xml'], $this->files());
    }

    public function testASourceGivingMoreThanTheMaximumIsRefused(): void
    {
        $path = $this->directory.'/feed.xml';
        $feed = new FeedPublisher(2)->publish(Items::source([Items::item('A'), Items::item('B')]), Items::channel(), new RssSerializer(), $path);
        self::assertSame(2, $feed->items);

        try {
            new FeedPublisher(2)->publish(Items::source([Items::item('A'), Items::item('B'), Items::item('C')]), Items::channel(), new RssSerializer(), $this->directory.'/other.xml');
            self::fail('The feed was written.');
        } catch (FeedNotWritten $feedNotWritten) {
            self::assertSame('A feed holds 2 items at most: the source gave more.', $feedNotWritten->getMessage());
        }

        self::assertSame(['feed.xml'], $this->files());
    }

    public function testTheMaximumIsAtLeastOne(): void
    {
        self::assertSame(1, new FeedPublisher(1)->publish(Items::source([Items::item()]), Items::channel(), new TsvSerializer(), $this->directory.'/a.tsv')->items);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A feed holds at least one item; 0 is no maximum.');

        new FeedPublisher(0);
    }

    public function testTheDirectoryExists(): void
    {
        $this->expectException(FeedNotWritten::class);
        $this->expectExceptionMessageIs(\sprintf('The feed is written in an existing, writable directory; "%s/missing" is not one.', $this->directory));

        new FeedPublisher()->publish(Items::source([]), Items::channel(), new TsvSerializer(), $this->directory.'/missing/feed.tsv');
    }

    public function testTheDirectoryIsWritable(): void
    {
        chmod($this->directory, 0o555);
        clearstatcache();

        if (is_writable($this->directory)) {
            self::markTestSkipped('The superuser writes in a read-only directory, as in a container.');
        }

        $this->expectException(FeedNotWritten::class);
        $this->expectExceptionMessageIs(\sprintf('The feed is written in an existing, writable directory; "%s" is not one.', $this->directory));

        new FeedPublisher()->publish(Items::source([]), Items::channel(), new TsvSerializer(), $this->directory.'/feed.tsv');
    }

    public function testAFeedThatCannotTakeThePlaceOfADirectoryIsRefused(): void
    {
        $path = $this->directory.'/feed.xml';
        mkdir($path);
        touch($path.'/keep');

        try {
            new FeedPublisher()->publish(Items::source([Items::item()]), Items::channel(), new RssSerializer(), $path);
            self::fail('The feed was written.');
        } catch (FeedNotWritten $feedNotWritten) {
            self::assertStringStartsWith(\sprintf('The feed written to "%s/.feed.xml.', $this->directory), $feedNotWritten->getMessage());
            self::assertStringEndsWith(\sprintf('" could not be moved to "%s".', $path), $feedNotWritten->getMessage());
        } finally {
            unlink($path.'/keep');
            rmdir($path);
        }

        self::assertSame([], $this->files());
    }

    public function testASerializerMayWriteNothingAroundTheItems(): void
    {
        $serializer = new class implements FeedSerializer {
            #[Override]
            public function header(FeedChannel $channel): string
            {
                return '';
            }

            #[Override]
            public function item(FeedItem $item): string
            {
                return $item->id."\n";
            }

            #[Override]
            public function footer(): string
            {
                return '';
            }
        };
        $path = $this->directory.'/ids.txt';

        $feed = new FeedPublisher()->publish(Items::source([Items::item('A'), Items::item('BB')]), Items::channel(), $serializer, $path);

        self::assertSame("A\nBB\n", file_get_contents($path));
        self::assertSame(5, $feed->bytes);
    }

    public function testEachFormatHasItsSerializerExtensionAndMediaType(): void
    {
        self::assertInstanceOf(RssSerializer::class, FeedFormat::Xml->serializer());
        self::assertInstanceOf(TsvSerializer::class, FeedFormat::Tsv->serializer());
        self::assertSame(['xml', 'tsv'], [FeedFormat::Xml->extension(), FeedFormat::Tsv->extension()]);
        self::assertSame(['application/rss+xml; charset=UTF-8', 'text/tab-separated-values; charset=UTF-8'], [FeedFormat::Xml->mediaType(), FeedFormat::Tsv->mediaType()]);
    }

    /**
     * @return list<string> the files of the directory, hidden ones included
     */
    private function files(): array
    {
        $files = scandir($this->directory);
        self::assertIsArray($files);

        return array_values(array_diff($files, ['.', '..']));
    }
}

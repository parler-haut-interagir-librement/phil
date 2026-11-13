<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Override;
use Phil\Epub\Book;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Exception\WriteFailed;
use Phil\EpubBundle\Application\BookSource;
use Phil\EpubBundle\Application\BookSources;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Event\EpubStored;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Application\Exception\UnknownBookSource;
use Phil\EpubBundle\Application\Message\WriteEpub;
use Phil\EpubBundle\Infrastructure\Messenger\EpubHandler;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;
use Phil\EpubBundle\Tests\Unit\Fixture\Directory;
use Phil\EpubBundle\Tests\Unit\Fixture\FailingStorage;
use Phil\EpubBundle\Tests\Unit\Fixture\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[CoversClass(EpubHandler::class)]
#[CoversClass(WriteEpub::class)]
#[CoversClass(EpubStored::class)]
final class EpubHandlerTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testWritesKeepsAndAnnouncesTheBook(): void
    {
        $directory = Directory::with([]);
        $events = new RecordingDispatcher();
        $handler = new EpubHandler(new Epubs(new DirectoryStorage($directory), new BookSources(['story' => new Books()])), $events);
        $handler(new WriteEpub('story', '42', 'stories/42.epub'));

        self::assertFileExists($directory.'/stories/42.epub');
        self::assertEquals([new EpubStored('story', '42', 'stories/42.epub', $directory.'/stories/42.epub')], $events->events);
    }

    public function testWorksWithoutDispatcher(): void
    {
        $directory = Directory::with([]);
        new EpubHandler(new Epubs(new DirectoryStorage($directory), new BookSources(['story' => new Books()])))(new WriteEpub('story', '1', '1.epub'));

        self::assertFileExists($directory.'/1.epub');
    }

    public function testAStorageThatFailsIsRetried(): void
    {
        $handler = new EpubHandler(new Epubs(new FailingStorage(), new BookSources(['story' => new Books()])), $events = new RecordingDispatcher());

        try {
            $handler(new WriteEpub('story', '42', '42.epub'));
            self::fail('The storage fails.');
        } catch (RecoverableMessageHandlingException $recoverableMessageHandlingException) {
            self::assertSame('The disk is full.', $recoverableMessageHandlingException->getMessage());
            self::assertInstanceOf(StorageFailed::class, $recoverableMessageHandlingException->getPrevious());
        }

        self::assertSame([], $events->events);
    }

    public function testAWriterThatFailsIsRetried(): void
    {
        $handler = new EpubHandler(new Epubs(new FailingStorage(), new BookSources(['full' => new class implements BookSource {
            public function build(string $id): Book
            {
                throw WriteFailed::because('No temporary file can be created.');
            }
        }])));

        try {
            $handler(new WriteEpub('full', '42', '42.epub'));
            self::fail('The writer fails.');
        } catch (RecoverableMessageHandlingException $recoverableMessageHandlingException) {
            self::assertInstanceOf(WriteFailed::class, $recoverableMessageHandlingException->getPrevious());
        }
    }

    public function testAnUnknownSourceIsNotRetried(): void
    {
        try {
            new EpubHandler(new Epubs(new FailingStorage()))(new WriteEpub('zine', '1', '1.epub'));
            self::fail('There is no source.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('There is no book source "zine"; the sources are "".', $unrecoverableMessageHandlingException->getMessage());
            self::assertInstanceOf(UnknownBookSource::class, $unrecoverableMessageHandlingException->getPrevious());
        }
    }

    public function testABookTheLibraryRefusesIsNotRetried(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);

        new EpubHandler(new Epubs(new FailingStorage(), new BookSources(['empty' => new class implements BookSource {
            public function build(string $id): Book
            {
                throw InvalidBook::because('A book has at least one chapter.');
            }
        }])))(new WriteEpub('empty', '1', '1.epub'));
    }
}

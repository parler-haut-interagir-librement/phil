<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Override;
use Phil\EpubBundle\Application\AsBookSource;
use Phil\EpubBundle\Application\BookSources;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Exception\UnknownBookSource;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;
use Phil\EpubBundle\Tests\Unit\Fixture\Directory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Epubs::class)]
#[CoversClass(BookSources::class)]
#[CoversClass(AsBookSource::class)]
#[CoversClass(UnknownBookSource::class)]
final class EpubsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testWritesAndKeepsABook(): void
    {
        $directory = Directory::with([]);
        $epubs = new Epubs(new DirectoryStorage($directory));
        $bytes = $epubs->write(Books::book());

        self::assertSame('application/epub+zip', Books::entries($bytes)['mimetype']);
        self::assertSame($directory.'/books/one.epub', $epubs->store(Books::book(), 'books/one.epub'));
        self::assertSame($bytes, file_get_contents($directory.'/books/one.epub'));
    }

    public function testBuildsABookFromASourceByName(): void
    {
        $source = new Books();
        $epubs = new Epubs(new DirectoryStorage('/unused'), new BookSources(['story' => $source, 'rules' => new Books()]));

        self::assertSame('Story 42', $epubs->build('story', '42')->metadata->title);
        self::assertSame(['42'], $source->built);
        self::assertSame(['story', 'rules'], $epubs->sources()->names());
        self::assertSame('story', new AsBookSource('story')->name);

        $this->expectException(UnknownBookSource::class);
        $this->expectExceptionMessageIsOrContains('There is no book source "zine"; the sources are "story", "rules".');

        $epubs->build('zine', '1');
    }

    public function testANumericNameIsAName(): void
    {
        self::assertSame(['2026'], new BookSources(['2026' => new Books()])->names());
    }

    public function testNoSourceByDefault(): void
    {
        self::assertSame([], new Epubs(new DirectoryStorage('/unused'))->sources()->names());
    }
}

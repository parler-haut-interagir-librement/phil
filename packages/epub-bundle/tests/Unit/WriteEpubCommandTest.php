<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Override;
use Phil\EpubBundle\Application\BookSources;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Infrastructure\Console\WriteEpubCommand;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;
use Phil\EpubBundle\Tests\Unit\Fixture\Directory;
use Phil\EpubBundle\Tests\Unit\Fixture\FailingStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(WriteEpubCommand::class)]
final class WriteEpubCommandTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testKeepsTheBookInTheStorage(): void
    {
        $directory = Directory::with([]);
        $tester = new CommandTester(new WriteEpubCommand(new Epubs(new DirectoryStorage($directory), new BookSources(['story' => new Books()]))));

        self::assertSame(Command::SUCCESS, $tester->execute(['source' => 'story', 'id' => '42']));
        self::assertSame(\sprintf("The book is kept at \"%s/story/42.epub\".\n", $directory), $tester->getDisplay());
        self::assertSame('Story 42', $this->title((string) file_get_contents($directory.'/story/42.epub')));
    }

    public function testWritesTheBookToAFile(): void
    {
        $directory = Directory::with([]);
        $tester = new CommandTester(new WriteEpubCommand(new Epubs(new FailingStorage(), new BookSources(['story' => new Books()]))));

        self::assertSame(Command::SUCCESS, $tester->execute(['source' => 'story', 'id' => '7', '--output' => $directory.'/out/7.epub']));
        self::assertSame(\sprintf("The book is written to \"%s/out/7.epub\".\n", $directory), $tester->getDisplay());
        self::assertSame('Story 7', $this->title((string) file_get_contents($directory.'/out/7.epub')));
    }

    public function testFailsWithTheReason(): void
    {
        $tester = new CommandTester(new WriteEpubCommand(new Epubs(new FailingStorage(), new BookSources(['story' => new Books()]))));

        self::assertSame(Command::FAILURE, $tester->execute(['source' => 'story', 'id' => '42']));
        self::assertSame("The disk is full.\n", $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['source' => 'zine', 'id' => '42']));
        self::assertSame("There is no book source \"zine\"; the sources are \"story\".\n", $tester->getDisplay());
    }

    public function testFailsWhenTheFileCannotBeWritten(): void
    {
        $tester = new CommandTester(new WriteEpubCommand(new Epubs(new FailingStorage(), new BookSources(['story' => new Books()]))));
        $file = Directory::with(['taken' => 'a file']).'/taken';

        self::assertSame(Command::FAILURE, $tester->execute(['source' => 'story', 'id' => '42', '-o' => $file.'/42.epub']));
        self::assertStringContainsString($file, $tester->getDisplay());
    }

    private function title(string $epub): string
    {
        preg_match('{<dc:title[^>]*>([^<]+)</dc:title>}', Books::entries($epub)['EPUB/package.opf'], $match);

        return $match[1] ?? '';
    }
}

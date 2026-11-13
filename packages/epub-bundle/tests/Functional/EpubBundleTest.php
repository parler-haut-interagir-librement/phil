<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Functional;

use Override;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Message\WriteEpub;
use Phil\EpubBundle\Tests\Functional\App\StoredBooks;
use Phil\EpubBundle\Tests\Functional\App\TestKernel;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;
use Phil\EpubBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The bundle in an application: a source registered by attribute, its fonts from phil/fonts-bundle, a message handled
 * at once, the book kept in the directory and announced.
 */
#[CoversNothing]
final class EpubBundleTest extends KernelTestCase
{
    private const string FONT = __DIR__.'/App/fonts/Sans.ttf';

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        new Filesystem()->dumpFile(self::FONT, Fonts::trueType('Example Sans'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        new Filesystem()->remove([self::FONT, \dirname(self::FONT)]);
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testAMessageWritesKeepsAndAnnouncesABook(): void
    {
        $kernel = self::bootKernel();
        self::assertInstanceOf(TestKernel::class, $kernel);
        new Filesystem()->remove($kernel->booksDirectory());
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new WriteEpub('story', '42', 'stories/42.epub'));

        $entries = Books::entries((string) file_get_contents($kernel->booksDirectory().'/stories/42.epub'));
        self::assertArrayHasKey('EPUB/fonts/Sans.ttf', $entries);
        self::assertStringContainsString('url("../fonts/Sans.ttf")', $entries['EPUB/css/fonts.css']);
        $stored = self::getContainer()->get(StoredBooks::class);
        self::assertInstanceOf(StoredBooks::class, $stored);
        self::assertSame($kernel->booksDirectory().'/stories/42.epub', $stored->events[0]->location ?? null);
    }

    public function testTheServicesAndTheCommand(): void
    {
        self::assertInstanceOf(Epubs::class, self::getContainer()->get(Epubs::class));
        self::assertTrue(new Application(self::bootKernel())->has('phil:epub:write'));
    }
}

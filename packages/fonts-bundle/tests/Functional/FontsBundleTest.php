<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Functional;

use Override;
use Phil\FontsBundle\Application\CatalogueEntry;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts;
use Phil\FontsBundle\Tests\Functional\App\TestKernel;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * The bundle in an application: the catalogue of the configured directory, the Twig function and the commands.
 */
#[CoversNothing]
final class FontsBundleTest extends KernelTestCase
{
    private const string FONTS = __DIR__.'/App/fonts';

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        new Filesystem()->dumpFile(self::FONTS.'/Example Sans.ttf', Fonts::trueType('Example Sans'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        new Filesystem()->remove(self::FONTS.'/Example Sans.ttf');
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheCatalogueReadsTheConfiguredDirectory(): void
    {
        $catalogue = self::getContainer()->get(FontCatalogue::class);

        self::assertInstanceOf(FontCatalogue::class, $catalogue);
        self::assertSame(['Example Sans.ttf'], array_map(static fn (CatalogueEntry $entry): string => $entry->path, $catalogue->entries()));
        self::assertInstanceOf(PdfFonts::class, self::getContainer()->get(PdfFonts::class));
    }

    public function testTheTwigFunctionWritesTheRules(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertStringContainsString('url("/fonts/Example%20Sans.ttf") format("truetype")', $twig->render('page.html.twig'));
    }

    public function testTheCommandsAreRegistered(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('phil:fonts:list'));
        $tester->execute([]);

        self::assertStringContainsString('Example Sans', $tester->getDisplay());
        self::assertTrue($application->has('phil:fonts:licences'));
        self::assertTrue($application->has('phil:fonts:css'));
    }
}

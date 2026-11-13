<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Functional;

use Override;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Phil\PrintPreflightBundle\Tests\Functional\App\TestKernel;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The bundle in an application: the profile of the configuration, the check of the application, and a font named by
 * its family found in the catalogue of phil/fonts-bundle.
 */
#[CoversNothing]
final class PrintPreflightBundleTest extends KernelTestCase
{
    private const string FONT = __DIR__.'/App/fonts/Restricted.ttf';

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        new Filesystem()->dumpFile(self::FONT, Fonts::trueType('Restricted Sans', fsType: 0x0002));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        new Filesystem()->remove(self::FONT);
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testChecksAMeasuredDocument(): void
    {
        $documents = self::getContainer()->get(MeasuredDocument::class);
        $preflighter = self::getContainer()->get(Preflighter::class);
        self::assertInstanceOf(MeasuredDocument::class, $documents);
        self::assertInstanceOf(Preflighter::class, $preflighter);

        $report = $preflighter->check($documents->fromArray(['unit' => 'mm', 'pages' => [['width' => 148, 'height' => 210, 'bleed' => 3]], 'fonts' => [['family' => 'Restricted Sans']]]));

        self::assertSame(['page-count.multiple', 'font.restricted', 'house.ink-limit'], array_map(static fn (Finding $finding): string => $finding->code, $report->findings));
    }

    public function testTheCommandIsRegistered(): void
    {
        self::assertTrue(new Application(self::bootKernel())->has('phil:print-preflight:check'));
    }
}

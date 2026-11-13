<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Phil\Fonts\Css\FontDisplay;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFaces;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Application\Licences;
use Phil\FontsBundle\Infrastructure\Console\CheckLicencesCommand;
use Phil\FontsBundle\Infrastructure\Console\FontFacesCommand;
use Phil\FontsBundle\Infrastructure\Console\ListFontsCommand;
use Phil\FontsBundle\Infrastructure\DependencyInjection\FontsServices;
use Phil\FontsBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\FontsBundle\Infrastructure\Media\MediaFonts;
use Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts;
use Phil\FontsBundle\Infrastructure\Twig\FontsExtension;
use Phil\FontsBundle\PhilFontsBundle;
use Phil\MediaBundle\Application\MediaLibrary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilFontsBundle::class)]
#[CoversClass(FontsServices::class)]
#[CoversClass(RequiredServicesPass::class)]
final class PhilFontsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([FontFiles::class, 'withAvailableBrotli'], $container->getDefinition(FontFiles::class)->getFactory());
        self::assertEquals(['%kernel.project_dir%/public/fonts', new Reference(FontFiles::class)], $container->getDefinition(FontCatalogue::class)->getArguments());
        self::assertEquals([new Reference(FontCatalogue::class), '/fonts', FontDisplay::Swap], $container->getDefinition(FontFaces::class)->getArguments());
        self::assertEquals([new Reference(FontCatalogue::class), '%kernel.project_dir%/config/fonts/licences.json'], $container->getDefinition(Licences::class)->getArguments());
        self::assertEquals([new Reference(FontCatalogue::class)], $container->getDefinition(ListFontsCommand::class)->getArguments());
        self::assertEquals([new Reference(Licences::class)], $container->getDefinition(CheckLicencesCommand::class)->getArguments());
        self::assertEquals([new Reference(FontFaces::class)], $container->getDefinition(FontFacesCommand::class)->getArguments());
        self::assertEquals([new Reference(FontFaces::class)], $container->getDefinition(FontsExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(FontsExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(FontsExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([new Reference(FontCatalogue::class)], $container->getDefinition(PdfFonts::class)->getArguments());
        self::assertEquals([new Reference(MediaLibrary::class), new Reference(FontFiles::class)], $container->getDefinition(MediaFonts::class)->getArguments());

        foreach ([ListFontsCommand::class, CheckLicencesCommand::class, FontFacesCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        foreach ([FontFiles::class, FontCatalogue::class, FontFaces::class, Licences::class, PdfFonts::class, MediaFonts::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['directory' => '/srv/fonts', 'public_path' => '/static/fonts', 'font_display' => 'fallback', 'licence_register' => '/srv/licences.json']);

        self::assertSame('/srv/fonts', $container->getDefinition(FontCatalogue::class)->getArgument(0));
        self::assertSame('/static/fonts', $container->getDefinition(FontFaces::class)->getArgument(1));
        self::assertSame(FontDisplay::Fallback, $container->getDefinition(FontFaces::class)->getArgument(2));
        self::assertSame('/srv/licences.json', $container->getDefinition(Licences::class)->getArgument(1));
    }

    public function testTheMediaFontsNeedTheMediaLibrary(): void
    {
        $container = new ContainerBuilder();
        new PhilFontsBundle()->build($container);
        $passes = array_values(array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RequiredServicesPass));
        self::assertCount(1, $passes);

        $without = $this->load([]);
        $passes[0]->process($without);
        self::assertFalse($without->hasDefinition(MediaFonts::class));
        self::assertTrue($without->hasDefinition(PdfFonts::class));

        $with = $this->load([]);
        $with->register(MediaLibrary::class, MediaLibrary::class);
        $passes[0]->process($with);
        self::assertTrue($with->hasDefinition(MediaFonts::class));
    }

    public function testRefusesAFontDisplayCssDoesNotKnow(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"auto", "block", "swap", "fallback", "optional"');

        $this->load(['font_display' => 'later']);
    }

    public function testRefusesAnEmptyDirectory(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['directory' => '']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilFontsBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_fonts', $extension->getAlias());
        $extension->load([$config], $container);

        return $container;
    }
}

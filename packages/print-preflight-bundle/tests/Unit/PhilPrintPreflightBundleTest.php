<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Phil\FontsBundle\Application\FontCatalogue;
use Phil\PrintPreflightBundle\Application\AsPreflightCheck;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use Phil\PrintPreflightBundle\Infrastructure\Console\PreflightCommand;
use Phil\PrintPreflightBundle\Infrastructure\DependencyInjection\FontCataloguePass;
use Phil\PrintPreflightBundle\Infrastructure\DependencyInjection\PrintPreflightServices;
use Phil\PrintPreflightBundle\Infrastructure\Fonts\CatalogueFontResolver;
use Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages;
use Phil\PrintPreflightBundle\PhilPrintPreflightBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilPrintPreflightBundle::class)]
#[CoversClass(PrintPreflightServices::class)]
#[CoversClass(FontCataloguePass::class)]
final class PhilPrintPreflightBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([PrintProfiles::class, 'fromConfiguration'], $container->getDefinition(PrintProfiles::class)->getFactory());
        self::assertSame([[], null], $container->getDefinition(PrintProfiles::class)->getArguments());
        self::assertEquals([new Reference(PrintProfiles::class), new TaggedIteratorArgument('phil_print_preflight.check')], $container->getDefinition(Preflighter::class)->getArguments());
        self::assertEquals([new Reference(FontResolver::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MeasuredDocument::class)->getArguments());
        self::assertEquals([new Reference(Preflighter::class), new Reference(MeasuredDocument::class)], $container->getDefinition(PreflightCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PreflightCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(FontCatalogue::class)], $container->getDefinition(CatalogueFontResolver::class)->getArguments());
        self::assertSame(CatalogueFontResolver::class, (string) $container->getAlias(FontResolver::class));
        self::assertTrue($container->hasDefinition(MediaImages::class));

        foreach ([PrintProfiles::class, Preflighter::class, MeasuredDocument::class, MediaImages::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertSame('phil_print_preflight.check', PrintPreflightServices::CHECK_TAG);
    }

    public function testTheProfilesOfTheConfiguration(): void
    {
        $container = $this->load(['default_profile' => 'booklet', 'profiles' => ['poster' => ['trim_sizes' => ['a4']], 'booklet' => ['trim_sizes' => ['a5'], 'page_multiple' => 4, 'severities' => ['page-count.multiple' => 'warning'], 'ignore' => ['colour.spot']]]]);
        [$profiles, $default] = $container->getDefinition(PrintProfiles::class)->getArguments();

        self::assertSame('booklet', $default);
        self::assertIsArray($profiles);
        $booklet = $profiles['booklet'];
        $poster = $profiles['poster'];
        self::assertIsArray($booklet);
        self::assertIsArray($poster);
        self::assertSame([['a5'], 4, '3mm', '5mm', ['page-count.multiple' => 'warning'], ['colour.spot']], [$booklet['trim_sizes'], $booklet['page_multiple'], $booklet['bleed'], $booklet['safety_margin'], $booklet['severities'], $booklet['ignore']]);
        self::assertSame([true, 150.0, 300.0, null, 1, null, 1, 'cmyk', false, [], []], [$poster['uniform_trim_size'], $poster['minimum_resolution'], $poster['target_resolution'], $poster['maximum_resolution'], $poster['minimum_pages'], $poster['maximum_pages'], $poster['page_multiple'], $poster['colour_workflow'], $poster['allow_spot_colours'], $poster['severities'], $poster['ignore']]);
        self::assertSame([300.0, '0.25pt', true, true], [$poster['maximum_ink_coverage'], $poster['minimum_line_width'], $poster['allow_overprint'], $poster['allow_transparency']]);
    }

    public function testProfileNamesAndValuesAreKeptAsWritten(): void
    {
        $container = $this->load(['profiles' => ['saddle-stitch' => ['colour_workflow' => 'rgb']]]);
        $profiles = $container->getDefinition(PrintProfiles::class)->getArgument(0);

        self::assertIsArray($profiles);
        self::assertSame(['saddle-stitch'], array_keys($profiles));
        self::assertIsArray($profiles['saddle-stitch']);
        self::assertSame('rgb', $profiles['saddle-stitch']['colour_workflow']);
    }

    public function testRefusesAProfileTheLibraryRefuses(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_print_preflight: The page count must be a multiple of at least 1; 0 given.');

        $this->load(['profiles' => ['zine' => ['page_multiple' => 0]]]);
    }

    public function testRefusesALengthItCannotRead(): void
    {
        try {
            $this->load(['profiles' => ['zine' => ['bleed' => '3cm']]]);
            self::fail('3cm is not a length the bundle reads.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_print_preflight: A length is a number and a unit, mm, in, pt or px, such as "3mm"; "3cm" is not.', $invalidConfigurationException->getMessage());
            self::assertNotNull($invalidConfigurationException->getPrevious());
        }
    }

    public function testRefusesAnUnknownDefault(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_print_preflight: There is no printer profile "zine"; the profiles are "default".');

        $this->load(['default_profile' => 'zine']);
    }

    public function testRefusesAnUnknownSeverity(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['profiles' => ['zine' => ['severities' => ['colour.spot' => 'fatal']]]]);
    }

    public function testChecksAreAddedByAttributeAndTheResolverNeedsTheCatalogue(): void
    {
        $container = new ContainerBuilder();
        new PhilPrintPreflightBundle()->build($container);
        $configurator = $container->getAttributeAutoconfigurators()[AsPreflightCheck::class][0];
        $definition = new ChildDefinition('');
        $configurator($definition, new AsPreflightCheck(), new ReflectionClass(self::class));
        self::assertTrue($definition->hasTag('phil_print_preflight.check'));

        $passes = array_values(array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof FontCataloguePass));
        self::assertCount(1, $passes);

        $without = $this->load([]);
        $passes[0]->process($without);
        self::assertFalse($without->hasDefinition(CatalogueFontResolver::class));
        self::assertFalse($without->hasAlias(FontResolver::class));

        $with = $this->load([]);
        $with->register(FontCatalogue::class, FontCatalogue::class);
        $passes[0]->process($with);
        self::assertTrue($with->hasDefinition(CatalogueFontResolver::class));
        self::assertTrue($with->hasAlias(FontResolver::class));

        $own = $this->load([]);
        $own->register('app.resolver', stdClass::class);
        $own->setAlias(FontResolver::class, 'app.resolver');
        $passes[0]->process($own);
        self::assertFalse($own->hasDefinition(CatalogueFontResolver::class));
        self::assertSame('app.resolver', (string) $own->getAlias(FontResolver::class));

        $removed = new ContainerBuilder();
        $passes[0]->process($removed);
        self::assertFalse($removed->hasAlias(FontResolver::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilPrintPreflightBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_print_preflight', $extension->getAlias());

        $extension->load([$config], $container);

        return $container;
    }
}

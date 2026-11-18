<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Level;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\NoPresentedDecks;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\NotesPageSize;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\SplitMode;
use Phil\Slides\Infrastructure\DependencyInjection\SlidesServicesPass;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Slides\PhilSlidesBundle;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSlidesBundle::class)]
#[CoversClass(SlidesServicesPass::class)]
final class PhilSlidesBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilSlides(): void
    {
        self::assertSame('phil_slides', $this->extension()->getAlias());
        self::assertSame(realpath(\dirname(__DIR__, 2).'/assets'), realpath(PhilSlidesBundle::ASSETS_DIR));
    }

    public function testTheServicesAreRegisteredWithTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertEquals([SplitMode::Auto, 2, 'notes', Slicer::DEFAULT_MAX_SLIDES, AspectRatio::Wide], $container->getDefinition(Slicer::class)->getArguments());
        self::assertEquals([new Reference(Ujo::class)], $container->getDefinition(SlideRenderer::class)->getArguments());
        self::assertEquals([
            new Reference(Slicer::class),
            new Reference(Ujo::class),
            new Reference(DefaultCompiler::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            Level::All,
            DeckReader::DEFAULT_MAX_SOURCE_BYTES,
        ], $container->getDefinition(DeckReader::class)->getArguments());
        self::assertTrue($container->getDefinition(DeckReader::class)->isPublic());
        self::assertEquals([new Reference(DeckReader::class), new Reference(SlideRenderer::class), 'fragment', null], $container->getDefinition(SlidesExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(SlidesExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(SlidesExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([new Reference('twig'), NotesPageSize::Letter], $container->getDefinition(DeckPrint::class)->getArguments());
        self::assertTrue($container->getDefinition(DeckPrint::class)->isPublic());
        self::assertEquals([new Reference(PagedMedia::class), new Reference(DeckPrint::class)], $container->getDefinition(DeckPdf::class)->getArguments());
        self::assertTrue($container->getDefinition(DeckPdf::class)->isPublic());
        self::assertSame(NoPresentedDecks::class, $container->getDefinition(PresentedDecks::class)->getClass());
        self::assertEquals([
            new Reference(PresentedDecks::class),
            new Reference('twig'),
            new Reference('router'),
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            'ROLE_SLIDES_PRESENTER',
            '@PhilSlides/layout.html.twig',
            new Reference(DeckPdf::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(PresenterController::class)->getArguments());
        self::assertSame(PhilSlidesBundle::DEFAULT_PRESENTER_ROLE, $container->getDefinition(PresenterController::class)->getArgument(4));
        self::assertTrue($container->getDefinition(PresenterController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(PresenterController::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('notes', 'speaker');
        $this->extension()->load([[
            'split' => 'both',
            'heading_level' => 6,
            'notes_class' => '%notes%',
            'fragment_class' => 'step',
            'aspect_ratio' => '4:3',
            'max_slides' => 10,
            'max_source_bytes' => 2048,
            'markup_level' => 'forum',
            'presenter_role' => 'ROLE_SPEAKER',
            'layout' => 'talks/layout.html.twig',
            'kit_subdirectory' => 'ui',
            'pdf' => ['notes_page_size' => 'a4'],
        ]], $container);

        self::assertEquals([SplitMode::Both, 6, 'speaker', 10, AspectRatio::Standard], $container->getDefinition(Slicer::class)->getArguments());
        self::assertSame(Level::Forum, $container->getDefinition(DeckReader::class)->getArgument(3));
        self::assertSame(2048, $container->getDefinition(DeckReader::class)->getArgument(4));
        self::assertSame('step', $container->getDefinition(SlidesExtension::class)->getArgument(2));
        self::assertSame(NotesPageSize::A4, $container->getDefinition(DeckPrint::class)->getArgument(1));
        self::assertSame('ui', $container->getDefinition(SlidesExtension::class)->getArgument(3));
        self::assertSame('ROLE_SPEAKER', $container->getDefinition(PresenterController::class)->getArgument(4));
        self::assertSame('talks/layout.html.twig', $container->getDefinition(PresenterController::class)->getArgument(5));
    }

    public function testABadNotesClassStopsTheBuild(): void
    {
        $this->expectException(InvalidOption::class);

        $this->load(['notes_class' => 'two words']);
    }

    public function testTheOptionsAreBounded(): void
    {
        foreach ([
            ['split' => 'pages'],
            ['heading_level' => 0],
            ['heading_level' => 7],
            ['notes_class' => ''],
            ['fragment_class' => 'two words'],
            ['fragment_class' => ''],
            ['fragment_class' => ['x']],
            ['fragment_class' => 5],
            ['fragment_class' => "step\n"],
            ['aspect_ratio' => '21:9'],
            ['max_slides' => 0],
            ['max_source_bytes' => 0],
            ['markup_level' => 'raw'],
            ['pdf' => ['notes_page_size' => 'a5']],
            ['presenter_role' => ''],
            ['presenter_role' => null],
            ['layout' => ''],
        ] as $config) {
            try {
                $this->load($config);
                self::fail('The configuration should be refused: '.json_encode($config));
            } catch (InvalidConfigurationException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $container = $this->load(['heading_level' => 1, 'max_slides' => 1, 'max_source_bytes' => 1, 'fragment_class' => str_repeat('f', 64)]);
        self::assertSame(1, $container->getDefinition(Slicer::class)->getArgument(1));
    }

    public function testTheFragmentClassMessageSaysWhy(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The class of the fragments is 1 to 64 letters, digits, "_" or "-"');

        $this->load(['fragment_class' => str_repeat('f', 65)]);
    }

    public function testTheAssetsAreMappedWithAssetMapper(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [PhilSlidesBundle::ASSETS_DIR => '@phil/slides']]]], $container->getExtensionConfig('framework'));

        $alone = new ContainerBuilder();
        $alone->registerExtension($extension);

        $extension->prepend($alone);
        self::assertFalse($alone->hasExtension('framework'));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilSlidesBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof SlidesServicesPass);
        self::assertCount(1, $passes);
    }

    public function testThePassKeepsWhatTheApplicationHas(): void
    {
        $container = $this->load([]);
        $container->register(Ujo::class);
        $container->register('twig');
        $container->register('router');
        $container->register(PagedMedia::class);

        new SlidesServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(DeckPrint::class));
        self::assertTrue($container->hasDefinition(DeckPdf::class));
        self::assertTrue($container->hasDefinition(PresenterController::class));
        self::assertSame('', $container->getDefinition(SlidesExtension::class)->getArgument(3));
    }

    public function testThePassNamesTheRecipesAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->register(Ujo::class);
        $container->register('twig');
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');

        new SlidesServicesPass()->process($container);

        self::assertSame('ui', $container->getDefinition(SlidesExtension::class)->getArgument(3));
        self::assertFalse($container->hasDefinition(PresenterController::class));

        $configured = $this->load(['kit_subdirectory' => 'kit']);
        $configured->register(Ujo::class);
        $configured->register('twig');
        $configured->setParameter('.ux_toolkit.components.subdirectory', 'ui');

        new SlidesServicesPass()->process($configured);

        self::assertSame('kit', $configured->getDefinition(SlidesExtension::class)->getArgument(3));

        $unnamed = $this->load([]);
        $unnamed->register(Ujo::class);
        $unnamed->register('twig');
        $unnamed->setParameter('.ux_toolkit.components.subdirectory', null);

        new SlidesServicesPass()->process($unnamed);

        self::assertSame('', $unnamed->getDefinition(SlidesExtension::class)->getArgument(3));
    }

    public function testThePassRemovesThePdfWithoutPagedMedia(): void
    {
        $container = $this->load([]);
        $container->register(Ujo::class);
        $container->setAlias('twig', 'my.twig');
        $container->register('my.twig');

        new SlidesServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(DeckPrint::class));
        self::assertFalse($container->hasDefinition(DeckPdf::class));
    }

    public function testThePassRemovesThePrintWithoutTwig(): void
    {
        $container = $this->load([]);
        $container->register(Ujo::class);
        $container->register(PagedMedia::class);

        new SlidesServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(DeckPrint::class));
        self::assertFalse($container->hasDefinition(DeckPdf::class));
        self::assertFalse($container->hasDefinition(PresenterController::class));
        self::assertTrue($container->hasDefinition(SlidesExtension::class));
        self::assertNull($container->getDefinition(SlidesExtension::class)->getArgument(3));
    }

    public function testThePassStopsWithoutTheUjoService(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/slides renders each slide with the Ujo service of phil/ujo-bundle, which is not registered: enable Phil\UjoBundle\PhilUjoBundle in config/bundles.php.');

        new SlidesServicesPass()->process($this->load([]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSlidesBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}

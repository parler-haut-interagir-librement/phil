<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Unit;

use Closure;
use Phil\QrCode\ErrorCorrection;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Http\HttpMermaidService;
use Phil\SvgBlocks\Renderer\MermaidRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocksBundle\Application\Handler\RenderDeferredMarkupHandler;
use Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup;
use Phil\SvgBlocksBundle\Infrastructure\DependencyInjection\StoredMarkupPass;
use Phil\SvgBlocksBundle\Infrastructure\DependencyInjection\SvgBlocksServices;
use Phil\SvgBlocksBundle\Infrastructure\Form\StoredMarkupTypeExtension;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewRequest;
use Phil\SvgBlocksBundle\Infrastructure\Messenger\MarkupScheduler;
use Phil\SvgBlocksBundle\PhilSvgBlocksBundle;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessengerBundle;

#[CoversClass(PhilSvgBlocksBundle::class)]
#[CoversClass(SvgBlocksServices::class)]
#[CoversClass(StoredMarkupPass::class)]
final class PhilSvgBlocksBundleTest extends TestCase
{
    public function testTheDefaultServices(): void
    {
        $container = $this->load([]);
        $markup = $container->getDefinition(BlockMarkup::class);
        $renderers = $this->renderers($markup);

        self::assertSame('phil_svg_blocks', $this->extension()->getAlias());
        self::assertEquals(['%kernel.secret%'], $container->getDefinition(Signer::class)->getArguments());
        self::assertSame([Closure::class, 'fromCallable'], $container->getDefinition(SvgBlocksServices::TRUSTS)->getFactory());
        self::assertEquals([[new Reference(Signer::class), 'trusts']], $container->getDefinition(SvgBlocksServices::TRUSTS)->getArguments());
        self::assertTrue($markup->isPublic());
        self::assertEquals(new Reference(Signer::class), $markup->getArgument(0));
        self::assertSame(['qrCode', 'chart', 'math'], array_keys($renderers));
        self::assertSame(['mermaid'], $markup->getArgument(2));
        self::assertFalse($container->hasDefinition(HttpMermaidService::class));
        self::assertSame('M', $this->inner($renderers['qrCode'], 1)->getArgument(0));
        self::assertSame([ErrorCorrection::class, 'from'], $this->inner($renderers['qrCode'], 1)->getFactory());
        self::assertSame([640, 400], $this->inner($renderers['chart'], 0)->getArguments());
        self::assertSame([20_000, 64, 1_000], $this->inner($renderers['math'], 0)->getArguments());
        self::assertEquals([new Reference(BlockMarkup::class), new Reference(Ujo::class)], $container->getDefinition(StoredMarkupTypeExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(StoredMarkupTypeExtension::class)->hasTag('form.type_extension'));
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(PreviewRequest::class)->getArguments());
        self::assertEquals([new Reference(BlockMarkup::class), new Reference(PreviewRequest::class), 65_536], $container->getDefinition(PreviewController::class)->getArguments());
        self::assertTrue($container->getDefinition(PreviewController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(PreviewController::class)->isPublic());
        self::assertEquals([new Reference('messenger.default_bus')], $container->getDefinition(MarkupScheduler::class)->getArguments());
        self::assertTrue($container->getDefinition(MarkupScheduler::class)->isPublic());
        self::assertNull($container->getParameter(SvgBlocksServices::DOCUMENTS));
        self::assertSame([['handles' => RenderDeferredMarkup::class]], $container->getDefinition(RenderDeferredMarkupHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([null, new Reference(BlockMarkup::class)], $container->getDefinition(RenderDeferredMarkupHandler::class)->getArguments());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'secret' => 'mine',
            'qr_code' => ['error_correction' => 'H'],
            'chart' => ['width' => 100, 'height' => 4000],
            'math' => ['max_length' => 1, 'max_depth' => 1, 'max_expansions' => 1],
            'mermaid' => ['url' => 'http://mermaid:3000', 'timeout' => 2.5, 'async' => false, 'documents' => 'app.documents'],
            'preview' => ['max_bytes' => 10],
        ]);
        $markup = $container->getDefinition(BlockMarkup::class);
        $renderers = $this->renderers($markup);

        self::assertEquals(['mine'], $container->getDefinition(Signer::class)->getArguments());
        self::assertSame('H', $this->inner($renderers['qrCode'], 1)->getArgument(0));
        self::assertSame([100, 4000], $this->inner($renderers['chart'], 0)->getArguments());
        self::assertSame([1, 1, 1], $this->inner($renderers['math'], 0)->getArguments());
        self::assertEquals([new Reference('http_client'), 'http://mermaid:3000', 2.5], $container->getDefinition(HttpMermaidService::class)->getArguments());
        self::assertSame(MermaidRenderer::class, $renderers['mermaid']->getClass());
        self::assertEquals(new Reference(HttpMermaidService::class), $renderers['mermaid']->getArgument(0));
        $sanitizer = $this->inner($renderers['mermaid'], 1);
        self::assertSame(SvgSanitizer::class, $sanitizer->getClass());
        self::assertSame(Policy::class, $this->inner($sanitizer, 0)->getClass());
        self::assertSame(['$styleElements' => true], $this->inner($sanitizer, 0)->getArguments());
        self::assertSame([], $markup->getArgument(2));
        self::assertSame('app.documents', $container->getParameter(SvgBlocksServices::DOCUMENTS));
        self::assertSame(10, $container->getDefinition(PreviewController::class)->getArgument(2));
        self::assertFalse($this->load(['preview' => false])->hasDefinition(PreviewController::class));
        self::assertFalse($this->load(['preview' => ['enabled' => false]])->hasDefinition(PreviewRequest::class));
    }

    public function testRefusesAConfigurationOutOfRange(): void
    {
        self::assertSame([4000, 100], $this->inner($this->renderers($this->load(['chart' => ['width' => 4000, 'height' => 100]])->getDefinition(BlockMarkup::class))['chart'], 0)->getArguments());
        self::assertSame(1, $this->load(['preview' => ['max_bytes' => 1]])->getDefinition(PreviewController::class)->getArgument(2));

        foreach ([['chart' => ['width' => 4001]], ['chart' => ['height' => 99]], ['secret' => ''], ['qr_code' => ['error_correction' => 'X']], ['chart' => ['width' => 99]], ['chart' => ['height' => 4001]], ['math' => ['max_length' => 0]], ['math' => ['max_depth' => 0]], ['math' => ['max_expansions' => 0]], ['mermaid' => ['timeout' => 0.0]], ['preview' => ['max_bytes' => 0]]] as $config) {
            try {
                $this->load($config);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheTokenGoesToFrameworkBundleAndTheControllerToAssetMapperBundle(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkStub());

        $assetMapper = new AssetMapperBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $assetMapper);
        $container->registerExtension($assetMapper);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [PreviewRequest::TOKEN_ID]]]], $container->getExtensionConfig('framework'));
        self::assertSame([['paths' => [PhilSvgBlocksBundle::CONTROLLERS_DIR => '@phil/svg-blocks-bundle']]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilSvgBlocksBundle::CONTROLLERS_DIR.'/preview_controller.js');

        // FrameworkBundle without AssetMapperBundle: nothing is written to the alias it would leave undefined.
        $withoutAssets = new ContainerBuilder();
        $withoutAssets->registerExtension(new FrameworkStub());

        $extension->prepend($withoutAssets);
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [PreviewRequest::TOKEN_ID]]]], $withoutAssets->getExtensionConfig('framework'));

        // AssetMapperBundle alone still maps the controller.
        $assetsAlone = new ContainerBuilder();
        $assetsAlone->registerExtension($assetMapper);

        $extension->prepend($assetsAlone);
        self::assertSame([], $assetsAlone->getExtensionConfig('framework'));
        self::assertSame([['paths' => [PhilSvgBlocksBundle::CONTROLLERS_DIR => '@phil/svg-blocks-bundle']]], $assetsAlone->getExtensionConfig('asset_mapper'));

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([[], []], [$alone->getExtensionConfig('framework'), $alone->getExtensionConfig('asset_mapper')]);
    }

    public function testTheControllerIsFetchedWhenItsElementAppears(): void
    {
        $package = json_decode((string) file_get_contents(\dirname(PhilSvgBlocksBundle::CONTROLLERS_DIR).'/package.json'), true, 16, \JSON_THROW_ON_ERROR);
        self::assertIsArray($package);
        $symfony = $package['symfony'] ?? null;
        self::assertIsArray($symfony);
        $controllers = $symfony['controllers'] ?? null;
        self::assertIsArray($controllers);
        self::assertSame(['main' => 'src/preview_controller.js', 'webpackMode' => 'eager', 'fetch' => 'lazy', 'enabled' => true], $controllers['preview'] ?? null);
    }

    public function testThePassDecoratesTheRendererAndWiresTheBackgroundPass(): void
    {
        $container = $this->load(['mermaid' => ['documents' => 'app.documents']]);
        $container->register(HtmlRenderer::class, HtmlRenderer::class);
        $container->register('messenger.default_bus');
        $container->register('security.csrf.token_manager');
        new StoredMarkupPass()->process($container);

        $decorator = $container->getDefinition(SvgBlocksServices::UJO_RENDERER);
        self::assertSame([HtmlRenderer::class, null, 0], $decorator->getDecoratedService());
        self::assertEquals([new Reference(SvgBlocksServices::UJO_RENDERER.'.inner'), 'withStoredMarkup'], $decorator->getFactory());
        self::assertEquals([new Reference(SvgBlocksServices::TRUSTS)], $decorator->getArguments());
        self::assertEquals(new Reference('app.documents'), $container->getDefinition(RenderDeferredMarkupHandler::class)->getArgument(0));
        self::assertTrue($container->hasDefinition(MarkupScheduler::class));
    }

    public function testThePassRemovesTheBackgroundPassWithoutDocumentsOrBus(): void
    {
        $withoutDocuments = $this->load([]);
        $withoutDocuments->register('messenger.default_bus');
        $withoutDocuments->register('security.csrf.token_manager');
        new StoredMarkupPass()->process($withoutDocuments);

        self::assertFalse($withoutDocuments->hasDefinition(RenderDeferredMarkupHandler::class));
        self::assertFalse($withoutDocuments->hasDefinition(MarkupScheduler::class));
        self::assertFalse($withoutDocuments->hasDefinition(SvgBlocksServices::UJO_RENDERER));

        $withoutBus = $this->load(['mermaid' => ['documents' => 'app.documents'], 'preview' => false]);
        new StoredMarkupPass()->process($withoutBus);
        self::assertFalse($withoutBus->hasDefinition(RenderDeferredMarkupHandler::class));
        self::assertFalse($withoutBus->hasDefinition(MarkupScheduler::class));

        $nothing = new ContainerBuilder();
        new StoredMarkupPass()->process($nothing);
        self::assertSame(['service_container'], array_keys($nothing->getDefinitions()));
    }

    public function testThePassStopsWhenThePreviewHasNoCsrfProtection(): void
    {
        $container = $this->load([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');

        new StoredMarkupPass()->process($container);
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilSvgBlocksBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof StoredMarkupPass));
    }

    /**
     * @return array<string, Definition>
     */
    private function renderers(Definition $markup): array
    {
        $renderers = $markup->getArgument(1);
        self::assertIsArray($renderers);

        /** @var array<string, Definition> $renderers */
        return $renderers;
    }

    private function inner(Definition $definition, int $argument): Definition
    {
        $inner = $definition->getArgument($argument);
        self::assertInstanceOf(Definition::class, $inner);

        return $inner;
    }

    public function testTheBackgroundPassIsRemovedBeforeMessengerWiresItsHandler(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilSvgBlocksBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $storedMarkup = array_search(StoredMarkupPass::class, $passes, true);
        self::assertIsInt($storedMarkup);
        self::assertLessThan($messenger, $storedMarkup);
    }

    public function testTheBackgroundPassRunsAfterThePassesOfHigherPriority(): void
    {
        $container = new ContainerBuilder();
        new PhilSvgBlocksBundle()->build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
            }
        }, priority: 2);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $storedMarkup = array_search(StoredMarkupPass::class, $passes, true);
        self::assertIsInt($storedMarkup);
        self::assertStringContainsString('@anonymous', $passes[$storedMarkup - 1]);
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
        $extension = new PhilSvgBlocksBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

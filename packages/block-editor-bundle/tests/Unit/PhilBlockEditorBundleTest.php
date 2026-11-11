<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit;

use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\DependencyInjection\BlockEditorServices;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController;
use Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\BlockEditorBundle\PhilBlockEditorBundle;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilBlockEditorBundle::class)]
#[CoversClass(BlockEditorServices::class)]
final class PhilBlockEditorBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_block_editor', $this->extension()->getAlias());
        self::assertSame(['service_container', Toolset::class, ToolsetNormalizers::class, Endpoints::class, EditorRequest::class, BlockEditorType::class, ImageUploadController::class, ReferenceSearchController::class], array_keys($container->getDefinitions()));
        self::assertSame([Toolset::class, 'preset'], $container->getDefinition(Toolset::class)->getFactory());
        self::assertSame(['full', null, null], $container->getDefinition(Toolset::class)->getArguments());
        self::assertEquals([new Reference(BlockTypeRegistry::class)], $container->getDefinition(ToolsetNormalizers::class)->getArguments());
        self::assertEquals([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), []], $container->getDefinition(Endpoints::class)->getArguments());
        self::assertEquals([
            new Reference(Ujo::class),
            new Reference(ToolsetNormalizers::class),
            new Reference(Toolset::class),
            new Reference(Endpoints::class),
            PhilBlockEditorBundle::COLORS,
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(EditorRequest::class),
        ], $container->getDefinition(BlockEditorType::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager'), new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'IS_AUTHENTICATED'], $container->getDefinition(EditorRequest::class)->getArguments());
        self::assertTrue($container->getDefinition(BlockEditorType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(EditorRequest::class), null, 10_485_760], $container->getDefinition(ImageUploadController::class)->getArguments());
        self::assertEquals([new Reference(EditorRequest::class), null, null], $container->getDefinition(ReferenceSearchController::class)->getArguments());

        foreach ([ImageUploadController::class, ReferenceSearchController::class] as $controller) {
            self::assertTrue($container->getDefinition($controller)->hasTag('controller.service_arguments'));
            self::assertTrue($container->getDefinition($controller)->isPublic());
        }

        self::assertSame(['accent', 'muted', 'success', 'warning', 'danger'], PhilBlockEditorBundle::COLORS);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'preset' => 'reduced',
            'tools' => ['paragraph', 'quote'],
            'inline_tools' => ['bold'],
            'colors' => ['brand'],
            'image_uploader' => 'app.uploader',
            'mention_search' => 'app.people',
            'link_search' => 'app.pages',
            'max_upload_bytes' => 5,
            'access' => 'ROLE_EDITOR',
        ]);

        self::assertSame(['reduced', ['paragraph', 'quote'], ['bold']], $container->getDefinition(Toolset::class)->getArguments());
        self::assertSame([Endpoints::IMAGE, Endpoints::MENTIONS, Endpoints::LINKS], $container->getDefinition(Endpoints::class)->getArgument(1));
        self::assertSame(['brand'], $container->getDefinition(BlockEditorType::class)->getArgument(4));
        self::assertSame('ROLE_EDITOR', $container->getDefinition(EditorRequest::class)->getArgument(2));
        self::assertNull($this->load(['access' => null])->getDefinition(EditorRequest::class)->getArgument(2), 'the routes left to the application');
        self::assertEquals([new Reference(EditorRequest::class), new Reference('app.uploader'), 5], $container->getDefinition(ImageUploadController::class)->getArguments());
        self::assertEquals([new Reference(EditorRequest::class), new Reference('app.people'), new Reference('app.pages')], $container->getDefinition(ReferenceSearchController::class)->getArguments());
        self::assertSame([Endpoints::LINKS], $this->load(['link_search' => 'app.pages'])->getDefinition(Endpoints::class)->getArgument(1));
        self::assertEquals([new Reference(EditorRequest::class), null, new Reference('app.pages')], $this->load(['link_search' => 'app.pages'])->getDefinition(ReferenceSearchController::class)->getArguments());
    }

    public function testTheLimitsOfTheConfiguration(): void
    {
        $container = $this->load(['preset' => 'full', 'max_upload_bytes' => 1]);

        self::assertSame(['full', null, null], $container->getDefinition(Toolset::class)->getArguments());
        self::assertSame(1, $container->getDefinition(ImageUploadController::class)->getArgument(2));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['preset' => 'tiny'], ['tools' => ['statblock']], ['tools' => ['header']], ['inline_tools' => ['footnoteReference']], ['max_upload_bytes' => 0]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_block_editor', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheFormThemeAndTheAssetsArePrepended(): void
    {
        $extension = new PhilBlockEditorBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtension());
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['form_themes' => ['@PhilBlockEditor/form/block_editor.html.twig']]], $container->getExtensionConfig('twig'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_block_editor']]], ['asset_mapper' => ['paths' => [
            PhilBlockEditorBundle::CONTROLLERS_DIR => '@phil/block-editor-bundle',
            PhilBlockEditorBundle::STYLES_DIR => 'phil-block-editor',
        ]]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilBlockEditorBundle::CONTROLLERS_DIR.'/editor_controller.js');
        self::assertFileExists(PhilBlockEditorBundle::STYLES_DIR.'/block-editor.css');
        self::assertFileExists(new PhilBlockEditorBundle()->getPath().'/templates/form/block_editor.html.twig');

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], []], [$bare->getExtensionConfig('twig'), $bare->getExtensionConfig('framework')]);
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
        $extension = new PhilBlockEditorBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

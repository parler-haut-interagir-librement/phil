<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit;

use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\MediaBundle\Application\Contract\SvgPurifier;
use Phil\MediaBundle\Application\DeleteBehaviour;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaReferences;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Application\Transform\Transformer;
use Phil\MediaBundle\Application\Transform\Transformers;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Infrastructure\Admin\ExplorerAccess;
use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Phil\MediaBundle\Infrastructure\Console\PlaceholdersCommand;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaReferences;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Doctrine\MediaType;
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaFieldConfigurator;
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;
use Phil\MediaBundle\Infrastructure\Http\MediaController;
use Phil\MediaBundle\Infrastructure\Image\BinaryEncoder;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Image\ImageProcessors;
use Phil\MediaBundle\Infrastructure\Profiler\MediaDataCollector;
use Phil\MediaBundle\Infrastructure\Profiler\MediaTrace;
use Phil\MediaBundle\Infrastructure\Profiler\TraceableImageProcessor;
use Phil\MediaBundle\Infrastructure\Profiler\TraceableMediaStorage;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Storage\FlysystemStorage;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Infrastructure\Svg\SvgMinifier;
use Phil\MediaBundle\Infrastructure\Twig\Component\Img;
use Phil\MediaBundle\Infrastructure\Twig\Component\Picture;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\MediaBundle\Infrastructure\Twig\ResponsiveImages;
use Phil\MediaBundle\Infrastructure\Validator\MediaFileValidator;
use Phil\MediaBundle\PhilMediaBundle;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMediaBundle::class)]
final class PhilMediaBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([['storage_dir' => '/srv/media', 'secret' => 's3cret', 'url_ttl' => 600, 'max_pixels' => 1_000_000, 'formats' => ['thumb' => ['width' => 320, 'height' => 200], 'hero' => ['width' => 1600, 'height' => 900, 'mode' => 'fit', 'quality' => 60]]]]);
        $clock = new Reference('clock');
        $repository = new Reference(MediaRepository::class);

        self::assertSame('phil_media', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineMediaRepository::class, FilesystemStorage::class, GdImageProcessor::class, UrlSigner::class, DoctrineMediaReferences::class, SvgMinifier::class, SanitizingSvgPurifier::class, MediaLibrary::class, MediaController::class, PlaceholdersCommand::class, MediaFileValidator::class, MediaPickerType::class, MediaExtension::class, ExplorerAccess::class, MediaAdminController::class, ResponsiveImages::class, MediaFieldConfigurator::class, Img::class, Picture::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineMediaRepository::class)->getArguments());
        self::assertSame(DoctrineMediaRepository::class, (string) $container->getAlias(MediaRepository::class));
        self::assertTrue($container->getAlias(MediaRepository::class)->isPublic());
        self::assertSame(['/srv/media'], $container->getDefinition(FilesystemStorage::class)->getArguments());
        self::assertSame(FilesystemStorage::class, (string) $container->getAlias(MediaStorage::class));
        self::assertSame(GdImageProcessor::class, (string) $container->getAlias(ImageProcessor::class));
        self::assertSame(1_000_000, $container->getDefinition(GdImageProcessor::class)->getArgument(0));
        self::assertSame(['s3cret'], $container->getDefinition(UrlSigner::class)->getArguments());

        $library = $container->getDefinition(MediaLibrary::class);
        self::assertTrue($library->isPublic());
        self::assertEquals([$repository, new Reference(MediaStorage::class), new Reference(ImageProcessor::class)], \array_slice($library->getArguments(), 0, 3));
        self::assertEquals($clock, $library->getArgument(3));
        self::assertEquals(new Reference(MediaReferences::class), $library->getArgument(6));
        self::assertNull($library->getArgument(7));
        self::assertEquals(new Reference(SvgPurifier::class), $library->getArgument(8));
        self::assertSame(SanitizingSvgPurifier::class, (string) $container->getAlias(SvgPurifier::class));
        self::assertEquals([new Definition(SvgSanitizer::class), new Reference(SvgMinifier::class)], $container->getDefinition(SanitizingSvgPurifier::class)->getArguments());
        self::assertSame([3], $container->getDefinition(SvgMinifier::class)->getArguments());
        $formats = $library->getArgument(4);
        self::assertIsArray($formats);
        self::assertSame(['thumb', 'hero', 'admin_thumbnail'], array_keys($formats));
        self::assertInstanceOf(Definition::class, $formats['admin_thumbnail']);
        self::assertSame([[ImageFormat::class, 'of'], ['admin_thumbnail', 240, 240, 'fit', 75]], [$formats['admin_thumbnail']->getFactory(), $formats['admin_thumbnail']->getArguments()]);
        self::assertInstanceOf(Definition::class, $formats['hero']);
        self::assertSame([ImageFormat::class, 'of'], $formats['hero']->getFactory());
        self::assertSame(['hero', 1600, 900, 'fit', 60, []], $formats['hero']->getArguments());
        self::assertInstanceOf(Definition::class, $formats['thumb']);
        self::assertSame(['thumb', 320, 200, 'crop', 80, []], $formats['thumb']->getArguments());

        $controller = $container->getDefinition(MediaController::class);
        self::assertEquals([$repository, new Reference(MediaLibrary::class), new Reference(UrlSigner::class), $clock, new Reference(UserContentPolicy::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());

        self::assertSame(DoctrineMediaReferences::class, (string) $container->getAlias(MediaReferences::class));
        self::assertEquals([new Reference('doctrine.orm.entity_manager'), DeleteBehaviour::Restrict, []], $container->getDefinition(DoctrineMediaReferences::class)->getArguments());
        self::assertEquals([$repository], $container->getDefinition(MediaFileValidator::class)->getArguments());
        self::assertTrue($container->getDefinition(MediaFileValidator::class)->hasTag('validator.constraint_validator'));
        self::assertEquals([$repository, new Reference('router')], $container->getDefinition(MediaPickerType::class)->getArguments());
        self::assertTrue($container->getDefinition(MediaPickerType::class)->hasTag('form.type'));

        $admin = $container->getDefinition(MediaAdminController::class);
        self::assertEquals([$repository, new Reference(MediaLibrary::class), new Reference('twig'), new Reference('router'), new Reference(ExplorerAccess::class), '@PhilMedia/admin/layout.html.twig', 48, 33_554_432], $admin->getArguments());
        self::assertTrue($admin->hasTag('controller.service_arguments'));
        self::assertTrue($admin->isPublic());
        self::assertEquals([new Reference(MediaLibrary::class), new Reference(MediaExtension::class), $repository, new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ResponsiveImages::class)->getArguments());
        self::assertEquals([$repository, new Reference(MediaLibrary::class)], $container->getDefinition(PlaceholdersCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PlaceholdersCommand::class)->hasTag('console.command'));

        foreach (['Img' => Img::class, 'Picture' => Picture::class] as $name => $component) {
            self::assertEquals([new Reference(ResponsiveImages::class)], $container->getDefinition($component)->getArguments());
            self::assertSame([['key' => 'PhilMedia:'.$name, 'template' => '@PhilMedia/components/'.$name.'.html.twig']], $container->getDefinition($component)->getTag('twig.component'));
        }

        self::assertEquals([$repository], $container->getDefinition(MediaFieldConfigurator::class)->getArguments());
        self::assertTrue($container->getDefinition(MediaFieldConfigurator::class)->hasTag('ea.field_configurator'));
        self::assertFalse($container->hasDefinition(MediaDataCollector::class));

        $extension = $container->getDefinition(MediaExtension::class);
        self::assertEquals([new Reference('router'), new Reference(UrlSigner::class), $clock, 600], $extension->getArguments());
        self::assertSame([['twig.attribute_extension', [[]]], ['twig.runtime', [[]]]], [['twig.attribute_extension', [$extension->getTag('twig.attribute_extension')[0]]], ['twig.runtime', [$extension->getTag('twig.runtime')[0]]]]);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame(['%kernel.project_dir%/var/media'], $container->getDefinition(FilesystemStorage::class)->getArguments());
        self::assertSame(['%kernel.secret%'], $container->getDefinition(UrlSigner::class)->getArguments());
        $defaultFormats = $container->getDefinition(MediaLibrary::class)->getArgument(4);
        self::assertIsArray($defaultFormats);
        self::assertSame(['admin_thumbnail'], array_keys($defaultFormats));
        self::assertSame(25_000_000, $container->getDefinition(GdImageProcessor::class)->getArgument(0));
        self::assertNull($container->getDefinition(MediaExtension::class)->getArgument(3));
        self::assertSame(1, $this->load([['url_ttl' => 1]])->getDefinition(MediaExtension::class)->getArgument(3));

        foreach ([[['url_ttl' => 0], 'Should be greater than or equal to 1'], [['max_pixels' => 0], 'Should be greater than or equal to 1'], [['storage_dir' => ''], 'cannot contain an empty value'], [['secret' => ''], 'cannot contain an empty value'], [['formats' => ['a' => ['width' => 1, 'height' => 1, 'mode' => 'stretch']]], 'The value "stretch" is not allowed'], [['formats' => ['a' => ['width' => 1]]], 'A format is a box, with a width and a height, or a list of transformers.'], [['formats' => ['a' => ['height' => 1]]], 'A format is a box, with a width and a height, or a list of transformers.'], [['formats' => ['a' => ['transformers' => [['width' => 1]]]]], 'The child config "type" under "phil_media.formats.a.transformers.0" must be configured'], [['encoders' => ['webp' => '']], 'cannot contain an empty value'], [['encoders' => ['avif' => '']], 'cannot contain an empty value'], [['processor' => 'imagick'], 'The value "imagick" is not allowed'], [['placeholder' => ['width' => 3]], 'Should be greater than or equal to 4'], [['placeholder' => ['width' => 65]], 'Should be less than or equal to 64'], [['placeholder' => ['quality' => 0]], 'Should be greater than or equal to 1'], [['placeholder' => ['quality' => 101]], 'Should be less than or equal to 100'], [['svg' => ['precision' => -1]], 'Should be greater than or equal to 0'], [['svg' => ['precision' => 9]], 'Should be less than or equal to 8']] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testStorageEncodersTransformationsAndTheOriginal(): void
    {
        $container = $this->load([['filesystem' => 'media.storage', 'encoders' => ['webp' => '/usr/bin/cwebp', 'avif' => 'gd'], 'original' => ['quality' => 60, 'widths' => [640]], 'formats' => ['card' => ['quality' => 70, 'widths' => [320], 'transformers' => [['type' => 'crop', 'width' => 600, 'height' => 400], ['type' => 'expand', 'width' => 700, 'height' => 500, 'background' => '#ffffff']]]]]]);

        self::assertFalse($container->hasDefinition(FilesystemStorage::class));
        self::assertEquals([new Reference('media.storage')], $container->getDefinition(FlysystemStorage::class)->getArguments());
        self::assertSame(FlysystemStorage::class, (string) $container->getAlias(MediaStorage::class));

        $encoders = $container->getDefinition(GdImageProcessor::class)->getArgument(1);
        self::assertIsArray($encoders);
        self::assertSame(['webp'], array_keys($encoders));
        self::assertInstanceOf(Definition::class, $encoders['webp']);
        self::assertSame([[BinaryEncoder::class, 'of'], ['webp', '/usr/bin/cwebp']], [$encoders['webp']->getFactory(), $encoders['webp']->getArguments()]);

        $formats = $container->getDefinition(MediaLibrary::class)->getArgument(4);
        self::assertIsArray($formats);
        $card = $formats['card'] ?? null;
        self::assertInstanceOf(Definition::class, $card);
        self::assertSame([ImageFormat::class, 'transforming'], $card->getFactory());
        self::assertSame(['card', 70, [320]], [$card->getArgument(0), $card->getArgument(2), $card->getArgument(3)]);
        $transformers = $card->getArgument(1);
        self::assertIsArray($transformers);
        self::assertCount(2, $transformers);
        self::assertInstanceOf(Definition::class, $transformers[1]);
        $factory = $transformers[1]->getFactory();
        self::assertIsArray($factory);
        self::assertSame([Transformers::class, 'fromDefinition', Transformer::class, [['type' => 'expand', 'width' => 700, 'height' => 500, 'background' => '#ffffff']]], [$factory[0], $factory[1], $transformers[1]->getClass(), $transformers[1]->getArguments()]);

        $original = $container->getDefinition(MediaLibrary::class)->getArgument(5);
        self::assertInstanceOf(Definition::class, $original);
        self::assertSame([[ImageFormat::class, 'original'], [60, [640]]], [$original->getFactory(), $original->getArguments()]);

        $defaults = $this->load([]);
        $auto = $defaults->getDefinition(GdImageProcessor::class)->getArgument(1);
        self::assertIsArray($auto);
        self::assertSame(['webp', 'avif'], array_keys($auto));
        self::assertInstanceOf(Definition::class, $auto['avif']);
        self::assertSame([[BinaryEncoder::class, 'find'], ['avif'], BinaryEncoder::class], [$auto['avif']->getFactory(), $auto['avif']->getArguments(), $auto['avif']->getClass()]);
        $defaultOriginal = $defaults->getDefinition(MediaLibrary::class)->getArgument(5);
        self::assertInstanceOf(Definition::class, $defaultOriginal);
        self::assertSame([80, []], $defaultOriginal->getArguments());
        self::assertSame([], $this->load([['encoders' => ['webp' => 'gd', 'avif' => 'gd']]])->getDefinition(GdImageProcessor::class)->getArgument(1));
    }

    public function testTheProcessorThePlaceholderAndTheSvgImages(): void
    {
        $container = $this->load([['processor' => 'auto', 'vips_binary' => '/opt/vips/bin/vips', 'max_pixels' => 9000, 'placeholder' => ['width' => 24, 'quality' => 30], 'svg' => ['precision' => 2]]]);

        self::assertSame('phil_media.image_processor', (string) $container->getAlias(ImageProcessor::class));
        $processor = $container->getDefinition('phil_media.image_processor');
        $factory = $processor->getFactory();
        self::assertIsArray($factory);
        self::assertSame([ImageProcessors::class, 'choose', ImageProcessor::class], [$factory[0], $factory[1], $processor->getClass()]);
        self::assertEquals(['auto', '/opt/vips/bin/vips', 9000, new Reference(GdImageProcessor::class)], $processor->getArguments());

        $placeholder = $container->getDefinition(MediaLibrary::class)->getArgument(7);
        self::assertInstanceOf(Definition::class, $placeholder);
        self::assertSame([[ImageFormat::class, 'of'], ['placeholder', 24, 24, 'fit', 30]], [$placeholder->getFactory(), $placeholder->getArguments()]);
        self::assertSame([2], $container->getDefinition(SvgMinifier::class)->getArguments());

        $defaults = $this->load([['placeholder' => true]])->getDefinition(MediaLibrary::class)->getArgument(7);
        self::assertInstanceOf(Definition::class, $defaults);
        self::assertSame(['placeholder', 16, 16, 'fit', 40], $defaults->getArguments());

        $plain = $this->load([['svg' => ['purify' => false]]]);
        self::assertFalse($plain->hasDefinition(SanitizingSvgPurifier::class));
        self::assertFalse($plain->hasAlias(SvgPurifier::class));
        self::assertNull($plain->getDefinition(MediaLibrary::class)->getArgument(8));
        self::assertFalse($plain->hasDefinition('phil_media.image_processor'));

        $debug = new ContainerBuilder();
        $debug->setParameter('kernel.debug', true);
        $this->extension()->load([['processor' => 'vips']], $debug);
        self::assertEquals([new Reference('phil_media.image_processor'), new Reference(MediaTrace::class)], $debug->getDefinition(TraceableImageProcessor::class)->getArguments());
        self::assertEquals(['vips', null, 25_000_000, new Reference(GdImageProcessor::class)], $debug->getDefinition('phil_media.image_processor')->getArguments());
    }

    public function testDeletionBehavioursAndTheExplorer(): void
    {
        $container = $this->load([['delete_behaviour' => ['default' => 'set_null', 'fields' => ['App\Entity\Post.cover' => 'keep', 'App\Entity\Post.banner' => 'restrict']], 'admin' => ['layout' => 'admin.html.twig', 'page_size' => 12, 'max_upload_bytes' => 1000], 'formats' => ['admin_thumbnail' => ['width' => 100, 'height' => 100]]]]);

        self::assertEquals([new Reference('doctrine.orm.entity_manager'), DeleteBehaviour::SetNull, ['App\Entity\Post.cover' => DeleteBehaviour::Keep, 'App\Entity\Post.banner' => DeleteBehaviour::Restrict]], $container->getDefinition(DoctrineMediaReferences::class)->getArguments());
        self::assertSame(['admin.html.twig', 12, 1000], \array_slice($container->getDefinition(MediaAdminController::class)->getArguments(), 5));
        self::assertEquals(new Reference(ExplorerAccess::class), $container->getDefinition(MediaAdminController::class)->getArgument(4));
        self::assertEquals([new Reference('security.csrf.token_manager'), new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'ROLE_ADMIN'], $container->getDefinition(ExplorerAccess::class)->getArguments());
        self::assertSame('MEDIA_EDIT', $this->load([['admin' => ['attribute' => 'MEDIA_EDIT']]])->getDefinition(ExplorerAccess::class)->getArgument(2));
        $formats = $container->getDefinition(MediaLibrary::class)->getArgument(4);
        self::assertIsArray($formats);
        self::assertInstanceOf(Definition::class, $formats['admin_thumbnail']);
        self::assertSame(['admin_thumbnail', 100, 100, 'crop', 80, []], $formats['admin_thumbnail']->getArguments());

        foreach ([[['delete_behaviour' => ['default' => 'cascade']], 'The value "cascade" is not allowed'], [['delete_behaviour' => ['fields' => ['A.b' => 'cascade']]], 'The value "cascade" is not allowed'], [['admin' => ['layout' => '']], 'cannot contain an empty value'], [['admin' => ['page_size' => 0]], 'Should be greater than or equal to 1'], [['admin' => ['max_upload_bytes' => 0]], 'Should be greater than or equal to 1']] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testInDebugTheProfilerSeesTheStorageAndTheRenderings(): void
    {
        foreach ([[[], FilesystemStorage::class], [[['filesystem' => 'media.storage']], FlysystemStorage::class]] as [$configs, $storage]) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.debug', true);
            $this->extension()->load($configs, $container);

            self::assertSame(TraceableMediaStorage::class, (string) $container->getAlias(MediaStorage::class));
            self::assertEquals([new Reference($storage), new Reference(MediaTrace::class)], $container->getDefinition(TraceableMediaStorage::class)->getArguments());
            self::assertSame(TraceableImageProcessor::class, (string) $container->getAlias(ImageProcessor::class));
            self::assertEquals([new Reference(GdImageProcessor::class), new Reference(MediaTrace::class)], $container->getDefinition(TraceableImageProcessor::class)->getArguments());
            self::assertTrue($container->hasDefinition(MediaTrace::class));
            self::assertEquals([new Reference(MediaTrace::class)], $container->getDefinition(MediaDataCollector::class)->getArguments());
            self::assertSame([['template' => '@PhilMedia/profiler/collector.html.twig', 'id' => 'phil_media']], $container->getDefinition(MediaDataCollector::class)->getTag('data_collector'));
        }

        $quiet = new ContainerBuilder();
        $quiet->setParameter('kernel.debug', false);
        $this->extension()->load([], $quiet);
        self::assertFalse($quiet->hasDefinition(MediaDataCollector::class));
        self::assertSame(FilesystemStorage::class, (string) $quiet->getAlias(MediaStorage::class));
    }

    public function testTheMappingIsPrependedWithDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('doctrine'));

        $with = new ContainerBuilder();
        $with->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        foreach (['twig', 'framework'] as $alias) {
            $with->registerExtension(new class($alias) extends Extension {
                public function __construct(
                    private readonly string $alias,
                ) {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        $extension->prepend($with);
        self::assertSame([['dbal' => ['types' => ['phil_media' => MediaType::class]]], ['orm' => ['mappings' => ['PhilMediaBundle' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => \dirname(__DIR__, 2).'/src/Domain/Model', 'prefix' => 'Phil\MediaBundle\Domain\Model']]]]], $with->getExtensionConfig('doctrine'));
        self::assertSame([['form_themes' => ['@PhilMedia/form/media_picker.html.twig']]], $with->getExtensionConfig('twig'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_media_explorer']]], ['asset_mapper' => ['paths' => [\dirname(__DIR__, 2).'/src/../assets/src' => '@phil/media-bundle']]]], $with->getExtensionConfig('framework'));
        self::assertSame(['admin_thumbnail', '@PhilMedia/form/media_picker.html.twig', \dirname(__DIR__, 2).'/src/../assets/src'], [PhilMediaBundle::ADMIN_THUMBNAIL, PhilMediaBundle::FORM_THEME, PhilMediaBundle::CONTROLLERS_DIR]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilMediaBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}

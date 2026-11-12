<?php

declare(strict_types=1);

namespace Phil\MediaBundle;

use Doctrine\DBAL\Types\Type;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use Override;
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
use Phil\MediaBundle\Infrastructure\Admin\ExplorerAccess;
use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Phil\MediaBundle\Infrastructure\Console\PlaceholdersCommand;
use Phil\MediaBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
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
use Phil\SvgSanitizer\SvgSanitizer;
use ReflectionClass;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Attribute\AsTwigFunction;

/**
 * A media library: files and their versions in Doctrine and a directory or a Flysystem filesystem, images rendered to
 * formats in WebP, AVIF, JPEG or PNG by GD or libvips, served at signed URLs — under the policy of user content of
 * phil/browser-security-bundle when it is enabled —, with their license and what they owe to artificial intelligence,
 * as an IPTC digital source type with phil/creation-disclosure; tiny previews, SVG images purified by
 * phil/svg-sanitizer and served as images; the `Img` and `Picture` components, a file explorer and its picker behind a role and a stateless
 * CSRF token, the `phil_media` column type and its deletion behaviours, an EasyAdmin field, and a profiler panel, each
 * wired when what it needs is installed.
 */
final class PhilMediaBundle extends AbstractBundle
{
    public const string ADMIN_THUMBNAIL = 'admin_thumbnail';

    public const string FORM_THEME = '@PhilMedia/form/media_picker.html.twig';

    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_media';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('storage_dir')
                    ->info('The directory files are kept in, unless a Flysystem filesystem is named.')
                    ->defaultValue('%kernel.project_dir%/var/media')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('filesystem')
                    ->info('The service of a Flysystem filesystem files are kept on, in place of the directory: local, S3, SFTP.')
                    ->defaultNull()
                ->end()
                ->scalarNode('secret')
                    ->info('The secret media URLs are signed with.')
                    ->defaultValue('%kernel.secret%')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('url_ttl')
                    ->info('How many seconds a media URL stays valid; null, forever.')
                    ->min(1)
                    ->defaultNull()
                ->end()
                ->integerNode('max_pixels')
                    ->info('The largest image, width times height, rendered to a format; a larger one is refused before it is decoded.')
                    ->min(1)
                    ->defaultValue(GdImageProcessor::MAX_PIXELS)
                ->end()
                ->arrayNode('formats')
                    ->info('The formats images are rendered to: a box, { width: 320, height: 320, mode: crop }, or transformations, { transformers: [{ type: crop, width: 600, height: 400 }] }.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->validate()
                            ->ifTrue(static fn (array $format): bool => [] === $format['transformers'] && (null === $format['width'] || null === $format['height']))
                            ->thenInvalid('A format is a box, with a width and a height, or a list of transformers.')
                        ->end()
                        ->children()
                            ->integerNode('width')->defaultNull()->end()
                            ->integerNode('height')->defaultNull()->end()
                            ->enumNode('mode')->values(ImageFormat::MODES)->defaultValue('crop')->end()
                            ->integerNode('quality')->defaultValue(80)->end()
                            ->arrayNode('widths')
                                ->info('The narrower widths the format is also rendered at, for a srcset.')
                                ->integerPrototype()->end()
                            ->end()
                            ->arrayNode('transformers')
                                ->info('Applied in order, in place of the box.')
                                ->arrayPrototype()
                                    ->children()
                                        ->enumNode('type')->values(Transformers::TYPES)->isRequired()->end()
                                        ->integerNode('width')->end()
                                        ->integerNode('height')->end()
                                        ->integerNode('x')->end()
                                        ->integerNode('y')->end()
                                        ->enumNode('mode')->values(ImageFormat::MODES)->end()
                                        ->booleanNode('upscale')->end()
                                        ->scalarNode('background')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('original')
                    ->info('The file itself converted whole to another type: its quality and narrower widths.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('quality')->defaultValue(80)->end()
                        ->arrayNode('widths')->integerPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('delete_behaviour')
                    ->info('What deleting a medium does to a record that shows it in a column of type phil_media.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('default')->values(array_column(DeleteBehaviour::cases(), 'value'))->defaultValue(DeleteBehaviour::Restrict->value)->end()
                        ->arrayNode('fields')
                            ->info('By "Entity\\Class.field": restrict, set_null or keep.')
                            ->useAttributeAsKey('field')
                            ->enumPrototype()->values(array_column(DeleteBehaviour::cases(), 'value'))->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The file explorer and its picker, once their routes are imported.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')->defaultValue('@PhilMedia/admin/layout.html.twig')->cannotBeEmpty()->end()
                        ->integerNode('page_size')->min(1)->defaultValue(48)->end()
                        ->integerNode('max_upload_bytes')->min(1)->defaultValue(33_554_432)->end()
                        ->scalarNode('attribute')
                            ->info('The role, or the attribute of a voter given the collection or the medium, that opens the explorer.')
                            ->defaultValue(ExplorerAccess::DEFAULT_ATTRIBUTE)
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->enumNode('processor')
                    ->info('What renders images: "gd"; "vips", libvips through its command-line tools, which must be found; or "auto", libvips when found, else GD.')
                    ->values(ImageProcessors::CHOICES)
                    ->defaultValue('gd')
                ->end()
                ->scalarNode('vips_binary')
                    ->info('The path of vips, its vipsheader beside it; searched on the PATH when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('placeholder')
                    ->info('A tiny preview of each version of a raster image, a data: URL painted behind the Img and Picture components by their Stimulus controller while the image loads; phil:media:placeholders gives one to the images kept before.')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('width')->info('The width and height of the box the preview fits in, in pixels.')->min(4)->max(64)->defaultValue(16)->end()
                        ->integerNode('quality')->min(1)->max(100)->defaultValue(40)->end()
                    ->end()
                ->end()
                ->arrayNode('svg')
                    ->info('SVG images purified and made smaller before they are kept, when phil/svg-sanitizer is installed.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('purify')->defaultTrue()->end()
                        ->integerNode('precision')->info('The decimals kept in the coordinates of paths, points and transforms.')->min(0)->max(8)->defaultValue(3)->end()
                    ->end()
                ->end()
                ->arrayNode('encoders')
                    ->info('How WebP and AVIF are encoded: "auto", cwebp or avifenc when found on the PATH, else GD; "gd"; or the path of the binary.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('webp')->defaultValue('auto')->cannotBeEmpty()->end()
                        ->scalarNode('avif')->defaultValue('auto')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [MediaType::NAME => MediaType::class]]]);
        }

        if ($builder->hasExtension('twig') && class_exists(AbstractType::class)) {
            $container->extension('twig', ['form_themes' => [self::FORM_THEME]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [ExplorerAccess::TOKEN_ID]]]);
        }

        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::CONTROLLERS_DIR => '@phil/media-bundle']]]);
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilMediaBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\MediaBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{storage_dir: string, filesystem: ?string, secret: string, url_ttl: ?int, max_pixels: int, formats: array<string, array{width: ?int, height: ?int, mode: string, quality: int, widths: list<int>, transformers: list<array<string, mixed>>}>, original: array{quality: int, widths: list<int>}, delete_behaviour: array{default: string, fields: array<string, string>}, admin: array{layout: string, page_size: int<1, max>, max_upload_bytes: int<1, max>, attribute: string}, processor: string, vips_binary: ?string, placeholder: array{enabled: bool, width: int, quality: int}, svg: array{purify: bool, precision: int<0, 8>}, encoders: array{webp: string, avif: string}} $config */
        $services = $container->services();
        $clock = new Reference('clock');

        // The ports by reflection: the bundle class wires the adapter, and names no class of the domain. The repository
        // and the one that reads several media at once are both the Doctrine adapter.
        $services->set(DoctrineMediaRepository::class)->args([new Reference('doctrine.orm.entity_manager')]);
        $port = '';

        foreach (new ReflectionClass(DoctrineMediaRepository::class)->getInterfaces() as $interface) {
            $services->alias($interface->getName(), DoctrineMediaRepository::class)->public();
            // `MediaRepository`, which every service of the bundle takes, extends no other port.
            $port = [] === $interface->getInterfaceNames() ? $interface->getName() : $port;
        }

        $storage = null === $config['filesystem'] ? FilesystemStorage::class : FlysystemStorage::class;
        $services->set($storage)->args([null === $config['filesystem'] ? $config['storage_dir'] : new Reference($config['filesystem'])]);
        $services->alias(MediaStorage::class, $storage);

        $processor = $this->imageProcessor($config['encoders'], $config['processor'], $config['vips_binary'], $config['max_pixels'], $services, $builder);

        if (true === ($builder->getParameterBag()->all()['kernel.debug'] ?? false)) {
            // In debug, the profiler sees what the storage and the image processor did.
            $services->set(MediaTrace::class);
            $services->set(TraceableMediaStorage::class)->args([new Reference($storage), new Reference(MediaTrace::class)]);
            $services->alias(MediaStorage::class, TraceableMediaStorage::class);
            $services->set(TraceableImageProcessor::class)->args([new Reference($processor), new Reference(MediaTrace::class)]);
            $services->alias(ImageProcessor::class, TraceableImageProcessor::class);
            $services->set(MediaDataCollector::class)->args([new Reference(MediaTrace::class)])
                ->tag('data_collector', ['template' => MediaDataCollector::TEMPLATE, 'id' => 'phil_media']);
        }

        $services->set(UrlSigner::class)->args([$config['secret']]);

        $formats = [];

        foreach ($config['formats'] as $name => $format) {
            $formats[$name] = [] === $format['transformers']
                ? new Definition(ImageFormat::class, [$name, $format['width'], $format['height'], $format['mode'], $format['quality'], $format['widths']])->setFactory([ImageFormat::class, 'of'])
                : new Definition(ImageFormat::class, [$name, array_map(static fn (array $transformer): Definition => new Definition(Transformer::class, [$transformer])->setFactory([Transformers::class, 'fromDefinition']), $format['transformers']), $format['quality'], $format['widths']])->setFactory([ImageFormat::class, 'transforming']);
        }

        // The explorer's thumbnails, unless the application declares a format of that name.
        $formats[self::ADMIN_THUMBNAIL] ??= new Definition(ImageFormat::class, [self::ADMIN_THUMBNAIL, 240, 240, 'fit', 75])->setFactory([ImageFormat::class, 'of']);
        $original = new Definition(ImageFormat::class, [$config['original']['quality'], $config['original']['widths']])->setFactory([ImageFormat::class, 'original']);

        $services->set(DoctrineMediaReferences::class)->args([
            new Reference('doctrine.orm.entity_manager'),
            DeleteBehaviour::from($config['delete_behaviour']['default']),
            array_map(DeleteBehaviour::from(...), $config['delete_behaviour']['fields']),
        ]);
        $services->alias(MediaReferences::class, DoctrineMediaReferences::class);

        $placeholder = $config['placeholder']['enabled']
            ? new Definition(ImageFormat::class, ['placeholder', $config['placeholder']['width'], $config['placeholder']['width'], 'fit', $config['placeholder']['quality']])->setFactory([ImageFormat::class, 'of'])
            : null;
        $purifier = $this->svgPurifier($config['svg']['purify'], $config['svg']['precision'], $services);

        $services->set(MediaLibrary::class)
            ->args([new Reference($port), new Reference(MediaStorage::class), new Reference(ImageProcessor::class), $clock, $formats, $original, new Reference(MediaReferences::class), $placeholder, $purifier])
            ->public();
        // The policy of user content, when phil/browser-security-bundle is enabled.
        $services->set(MediaController::class)
            ->args([new Reference($port), new Reference(MediaLibrary::class), new Reference(UrlSigner::class), $clock, new Reference(UserContentPolicy::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->tag('controller.service_arguments')
            ->public();

        $services->set(PlaceholdersCommand::class)->args([new Reference($port), new Reference(MediaLibrary::class)])->tag('console.command');

        if (class_exists(ConstraintValidator::class)) {
            $services->set(MediaFileValidator::class)->args([new Reference($port)])->tag('validator.constraint_validator');
        }

        if (class_exists(AbstractType::class)) {
            $services->set(MediaPickerType::class)->args([new Reference($port), new Reference('router')])->tag('form.type');
        }

        if (class_exists(AsTwigFunction::class)) {
            $services->set(MediaExtension::class)
                ->args([new Reference('router'), new Reference(UrlSigner::class), $clock, $config['url_ttl']])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
            $services->set(ExplorerAccess::class)
                ->args([new Reference('security.csrf.token_manager'), new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['admin']['attribute']]);
            $services->set(MediaAdminController::class)
                ->args([new Reference($port), new Reference(MediaLibrary::class), new Reference('twig'), new Reference('router'), new Reference(ExplorerAccess::class), $config['admin']['layout'], $config['admin']['page_size'], $config['admin']['max_upload_bytes']])
                ->tag('controller.service_arguments')
                ->public();
            $services->set(ResponsiveImages::class)->args([new Reference(MediaLibrary::class), new Reference(MediaExtension::class), new Reference($port), new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        if (interface_exists(FieldConfiguratorInterface::class)) {
            $services->set(MediaFieldConfigurator::class)->args([new Reference($port)])->tag('ea.field_configurator');
        }

        if (class_exists(AsTwigComponent::class)) {
            foreach (['Img' => Img::class, 'Picture' => Picture::class] as $name => $component) {
                $services->set($component)->args([new Reference(ResponsiveImages::class)])
                    ->tag('twig.component', ['key' => 'PhilMedia:'.$name, 'template' => \sprintf('@PhilMedia/components/%s.html.twig', $name)]);
            }
        }
    }

    /**
     * GD with its encoders, and libvips in its place when the configuration chooses it: the service id of the processor.
     *
     * @param array<string, string> $encoderChoices by file type: `auto`, `gd` or the path of a binary
     */
    private function imageProcessor(array $encoderChoices, string $choice, ?string $vips, int $maxPixels, ServicesConfigurator $services, ContainerBuilder $builder): string
    {
        $encoders = [];

        foreach ($encoderChoices as $type => $encoder) {
            if ('gd' !== $encoder) {
                $encoders[$type] = 'auto' === $encoder
                    ? new Definition(BinaryEncoder::class, [$type])->setFactory([BinaryEncoder::class, 'find'])
                    : new Definition(BinaryEncoder::class, [$type, $encoder])->setFactory([BinaryEncoder::class, 'of']);
            }
        }

        $services->set(GdImageProcessor::class)->args([$maxPixels, $encoders]);
        $processor = GdImageProcessor::class;

        if ('gd' !== $choice) {
            $processor = 'phil_media.image_processor';
            $builder->setDefinition($processor, new Definition(ImageProcessor::class, [$choice, $vips, $maxPixels, new Reference(GdImageProcessor::class)])->setFactory([ImageProcessors::class, 'choose']));
        }

        $services->alias(ImageProcessor::class, $processor);

        return $processor;
    }

    /**
     * The SVG purifier, when phil/svg-sanitizer is installed and the configuration keeps it on.
     *
     * @param int<0, 8> $precision
     */
    private function svgPurifier(bool $purify, int $precision, ServicesConfigurator $services): ?Reference
    {
        if (!$purify || !class_exists(SvgSanitizer::class)) {
            return null;
        }

        $services->set(SvgMinifier::class)->args([$precision]);
        $services->set(SanitizingSvgPurifier::class)->args([new Definition(SvgSanitizer::class), new Reference(SvgMinifier::class)]);
        $services->alias(SvgPurifier::class, SanitizingSvgPurifier::class);

        return new Reference(SvgPurifier::class);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(ExplorerAccess::class, ExplorerAccess::TOKEN_ID));
    }
}

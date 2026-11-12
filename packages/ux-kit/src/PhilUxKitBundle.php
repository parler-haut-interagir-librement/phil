<?php

declare(strict_types=1);

namespace Phil\UxKit;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Override;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Type\ImageObject;
use Phil\UxKit\Application\Contract\PicturePropsProvider;
use Phil\UxKit\Application\ImageMigration;
use Phil\UxKit\Application\ImageOrigin;
use Phil\UxKit\Application\ImageTextCheck;
use Phil\UxKit\Application\PictureProps;
use Phil\UxKit\Infrastructure\Console\MigrateImagesCommand;
use Phil\UxKit\Infrastructure\DependencyInjection\ImageFiltersPass;
use Phil\UxKit\Infrastructure\LiipImagine\ImageFilters;
use Phil\UxKit\Infrastructure\StructuredData\ImageJsonLd;
use Phil\UxKit\Infrastructure\Twig\ImageExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

/**
 * The PHP the recipes of the kit call from Twig: `image_origin()`, which keeps the images of other hosts off the page
 * unless their host is listed, `image_sources()`, which reads the files of an image among the assets, `image_check()`,
 * which warns in development of a text alternative or a size left wrong, and the bridges of the images:
 * `image_filters()` to the filter sets of LiipImagine, `picture_props()` to the services tagged
 * `phil_ux_kit.picture_props`, and `image_json_ld()` to phil/structured-data, each when it is installed. With the
 * Console component, `phil:ux-kit:migrate-images` proposes these recipes in place of the images written by hand.
 */
final class PhilUxKitBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_ux_kit';

    /** The tag of the services that give the props of a picture for an object. */
    public const string PICTURE_PROPS_TAG = 'phil_ux_kit.picture_props';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ImageFiltersPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('image')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('hosts')
                            ->info('The hosts whose images a page may show, those under each included; every other host is refused.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->booleanNode('allow_localhost')
                            ->info('Whether localhost and the names under .localhost are hosts: true in development only.')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('check')
                            ->info('Whether a text alternative that is the name of the file or says only "image", and a missing width or height, are logged as warnings: in debug when null.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $image = \is_array($config['image'] ?? null) ? $config['image'] : [];
        $hosts = \is_array($image['hosts'] ?? null) ? array_values(array_filter($image['hosts'], is_string(...))) : [];

        $check = $image['check'] ?? $builder->getParameter('kernel.debug');

        $allowLocalhost = true === ($image['allow_localhost'] ?? false);

        $builder->registerForAutoconfiguration(PicturePropsProvider::class)->addTag(self::PICTURE_PROPS_TAG);

        $services = $container->services();
        $services->set(ImageOrigin::class)
            ->args([$hosts, $allowLocalhost]);
        $services->set(ImageTextCheck::class);
        $services->set(PictureProps::class)
            ->args([new TaggedIteratorArgument(self::PICTURE_PROPS_TAG)]);

        if (class_exists(Command::class)) {
            $services->set(ImageMigration::class);
            $services->set(MigrateImagesCommand::class)
                ->args([new ReferenceConfigurator(ImageMigration::class), '%kernel.project_dir%'])
                ->tag('console.command');
        }

        if (class_exists(CacheManager::class)) {
            $services->set(ImageFilters::class)
                ->args([new ReferenceConfigurator('liip_imagine.cache.manager'), new ReferenceConfigurator('liip_imagine.filter.configuration')]);
        }

        if (class_exists(ImageObject::class)) {
            $services->set(ImageJsonLd::class)
                ->args([
                    new ReferenceConfigurator('url_helper')->nullOnInvalid(),
                    $allowLocalhost,
                    new ReferenceConfigurator(NodeCollector::class)->nullOnInvalid(),
                ]);
        }

        $services->set(ImageExtension::class)
            ->args([
                new ReferenceConfigurator(ImageOrigin::class),
                new ReferenceConfigurator('request_stack')->nullOnInvalid(),
                new ReferenceConfigurator('asset_mapper')->nullOnInvalid(),
                true === $check ? new ReferenceConfigurator(ImageTextCheck::class) : null,
                true === $check ? new ReferenceConfigurator('logger')->nullOnInvalid() : null,
                new ReferenceConfigurator(ImageFilters::class)->nullOnInvalid(),
                new ReferenceConfigurator(PictureProps::class),
                new ReferenceConfigurator(ImageJsonLd::class)->nullOnInvalid(),
            ])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime')
            ->tag('monolog.logger', ['channel' => 'phil_ux_kit']);
    }
}

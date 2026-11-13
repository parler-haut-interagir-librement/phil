<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\DependencyInjection;

use Phil\PwaBundle\Application\Contract\AssetUrls;
use Phil\PwaBundle\Application\Contract\PrecacheProvider;
use Phil\PwaBundle\Application\ManifestDocument;
use Phil\PwaBundle\Application\ManifestReader;
use Phil\PwaBundle\Application\ServiceWorkerScript;
use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\Rule;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;
use Phil\PwaBundle\Infrastructure\Asset\PackagesAssetUrls;
use Phil\PwaBundle\Infrastructure\Asset\PlainAssetUrls;
use Phil\PwaBundle\Infrastructure\ConfiguredPwa;
use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Phil\PwaBundle\Infrastructure\Twig\PwaExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Config array{
 *     manifest: array<string, mixed>,
 *     service_worker: array{cache: string, scope: string, script: string, precache: list<string>, offline: bool, cache_rules: list<array{prefix: string, strategy: string}>, exclude: list<string>},
 *     layout: string,
 *     cache_max_age: int,
 * }
 */
final readonly class PwaServices
{
    public const string PRECACHE_TAG = 'phil_pwa.precache_provider';

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $worker = $config['service_worker'];
        $settings = ['cache' => $worker['cache'], 'scope' => $worker['scope'], 'cache_rules' => $worker['cache_rules'], 'exclude' => $worker['exclude'], 'offline' => $worker['offline']];

        try {
            $configured = new ConfiguredPwa($config['manifest'], $settings);
        } catch (InvalidManifest $invalidManifest) {
            throw new InvalidConfigurationException('phil_pwa: '.$invalidManifest->getMessage(), $invalidManifest->getCode(), previous: $invalidManifest);
        }

        foreach ($worker['precache'] as $source) {
            self::check(static fn (): string => Rule::source($source, 'precached path'), 'phil_pwa.service_worker.precache');
        }

        self::check(static fn (): string => Rule::assetPath($worker['script'], 'script of the service worker'), 'phil_pwa.service_worker.script');

        $container->registerForAutoconfiguration(PrecacheProvider::class)->addTag(self::PRECACHE_TAG);
        $container->register(ManifestReader::class, ManifestReader::class);
        $container->register(ConfiguredPwa::class, ConfiguredPwa::class)->setArguments([$config['manifest'], $settings, new Reference(ManifestReader::class)]);
        $container->register(Manifest::class, Manifest::class)->setFactory([new Reference(ConfiguredPwa::class), 'manifest'])->setPublic(true);
        $container->register(WorkerSettings::class, WorkerSettings::class)->setFactory([new Reference(ConfiguredPwa::class), 'worker']);

        $container->register(PlainAssetUrls::class, PlainAssetUrls::class);

        if (class_exists(Packages::class)) {
            $container->register(PackagesAssetUrls::class, PackagesAssetUrls::class)->setArguments([new Reference(AssetUrlsPass::PACKAGES)]);
            $container->setAlias(AssetUrls::class, PackagesAssetUrls::class)->setPublic(true);
        } else {
            $container->setAlias(AssetUrls::class, PlainAssetUrls::class)->setPublic(true);
        }

        $container->register(ManifestDocument::class, ManifestDocument::class)->setArguments([new Reference(AssetUrls::class)])->setPublic(true);
        $container->register(ServiceWorkerScript::class, ServiceWorkerScript::class)
            ->setArguments([
                new Reference(WorkerSettings::class),
                new Reference(AssetUrls::class),
                $worker['script'],
                $worker['precache'],
                new TaggedIteratorArgument(self::PRECACHE_TAG),
            ])
            ->setPublic(true);

        $container->register(PwaController::class, PwaController::class)
            ->setArguments([
                new Reference(Manifest::class),
                new Reference(ManifestDocument::class),
                new Reference(ServiceWorkerScript::class),
                $configured->worker()->scope,
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
                $config['cache_max_age'],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        $container->register(PwaExtension::class, PwaExtension::class)
            ->setArguments([new Reference(Manifest::class), new Reference('router'), $configured->worker()->scope])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
    }

    /**
     * @param callable(): string $check
     */
    private static function check(callable $check, string $path): void
    {
        try {
            $check();
        } catch (InvalidManifest $invalidManifest) {
            throw new InvalidConfigurationException($path.': '.$invalidManifest->getMessage(), $invalidManifest->getCode(), previous: $invalidManifest);
        }
    }
}

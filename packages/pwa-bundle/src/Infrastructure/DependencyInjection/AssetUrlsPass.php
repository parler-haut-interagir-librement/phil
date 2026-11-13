<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\PwaBundle\Application\Contract\AssetUrls;
use Phil\PwaBundle\Infrastructure\Asset\PackagesAssetUrls;
use Phil\PwaBundle\Infrastructure\Asset\PlainAssetUrls;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives the addresses of assets from the Asset component — and so from AssetMapper — when the application has it,
 * else from their logical paths under `/`.
 */
final readonly class AssetUrlsPass implements CompilerPassInterface
{
    public const string PACKAGES = 'assets.packages';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias(AssetUrls::class) || PackagesAssetUrls::class !== (string) $container->getAlias(AssetUrls::class)) {
            return;
        }

        if (!$container->has(self::PACKAGES)) {
            $container->setAlias(AssetUrls::class, PlainAssetUrls::class)->setPublic(true);
        }
    }
}

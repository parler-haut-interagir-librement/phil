<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\QrCodeBundle\Infrastructure\Ujo\QrCodeBlockRenderer;
use Phil\SvgBlocks\BlockMarkup;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * When phil/svg-blocks-bundle draws the blocks of UJO documents, its `qrCode` renderer becomes this bundle's, which
 * draws with the defaults of this configuration.
 */
final class SvgBlocksPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BlockMarkup::class) || !$container->hasDefinition(QrCodeBlockRenderer::class)) {
            return;
        }

        $markup = $container->getDefinition(BlockMarkup::class);
        $renderers = $markup->getArgument(1);

        if (\is_array($renderers)) {
            $markup->replaceArgument(1, ['qrCode' => new Reference(QrCodeBlockRenderer::class)] + $renderers);
        }
    }
}

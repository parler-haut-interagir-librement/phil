<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Ujo\Html\HtmlRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Decorates the `HtmlRenderer` of `phil/ujo-bundle` with the typographic filter, so every UJO document rendered by
 * the application — `Ujo::toHtml()`, the `ujo_html` filter — is fixed too. Without that renderer, the filter is
 * removed.
 */
final class UjoFilterPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(TypographyServices::UJO_FILTER)) {
            return;
        }

        if (!$container->hasDefinition(HtmlRenderer::class)) {
            $container->removeDefinition(TypographyServices::UJO_FILTER);

            return;
        }

        $container->register(TypographyServices::UJO_RENDERER, HtmlRenderer::class)
            ->setFactory([new Reference(TypographyServices::UJO_RENDERER.'.inner'), 'withFilter'])
            ->setArguments([new Reference(TypographyServices::UJO_FILTER)])
            ->setDecoratedService(HtmlRenderer::class);
    }
}

<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SvgBlocksBundle\Application\Handler\RenderDeferredMarkupHandler;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewRequest;
use Phil\SvgBlocksBundle\Infrastructure\Messenger\MarkupScheduler;
use Phil\Ujo\Html\HtmlRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Completes the services once every bundle is loaded: decorates the `HtmlRenderer` of `phil/ujo-bundle` so that
 * every document is drawn from its signed markup; gives the background pass the application's `MarkupDocuments`, or
 * removes it and its scheduler when there is none or no bus; and stops the container, with what to change, when the
 * preview is on and the CSRF protection off.
 */
final class StoredMarkupPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(HtmlRenderer::class)) {
            $container->register(SvgBlocksServices::UJO_RENDERER, HtmlRenderer::class)
                ->setFactory([new Reference(SvgBlocksServices::UJO_RENDERER.'.inner'), 'withStoredMarkup'])
                ->setArguments([new Reference(SvgBlocksServices::TRUSTS)])
                ->setDecoratedService(HtmlRenderer::class);
        }

        $this->backgroundPass($container);

        if ($container->hasDefinition(PreviewController::class) && !$container->has('security.csrf.token_manager')) {
            throw new LogicException(\sprintf('phil/svg-blocks-bundle checks its preview requests with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true — the token "%s" is stateless and needs no session — or turn the preview off with "phil_svg_blocks.preview.enabled: false".', PreviewRequest::TOKEN_ID));
        }
    }

    private function backgroundPass(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RenderDeferredMarkupHandler::class)) {
            return;
        }

        $documents = $container->getParameter(SvgBlocksServices::DOCUMENTS);

        if (!\is_string($documents) || !$container->has('messenger.default_bus')) {
            $container->removeDefinition(RenderDeferredMarkupHandler::class);
            $container->removeDefinition(MarkupScheduler::class);

            return;
        }

        $container->getDefinition(RenderDeferredMarkupHandler::class)->replaceArgument(0, new Reference($documents));
    }
}

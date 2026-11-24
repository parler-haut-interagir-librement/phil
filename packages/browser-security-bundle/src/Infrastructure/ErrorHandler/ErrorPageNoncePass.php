<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\ErrorHandler;

use Closure;
use Override;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

/**
 * Hands the nonces of the response to the HTML error page of the ErrorHandler component (`setNonce()`, Symfony 8.2):
 * its `<script>` and `<style>` elements, and those of the dumps it shows, carry the nonces the policy lists, so that a
 * policy without `'unsafe-inline'` still lets the page show. The nonces are read through closures when a page is
 * rendered, from the main request, never kept by the renderer between two requests.
 */
final readonly class ErrorPageNoncePass implements CompilerPassInterface
{
    public const string RENDERER = 'error_handler.error_renderer.html';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::RENDERER) || !$container->hasDefinition(ContentSecurityPolicyContext::class)) {
            return;
        }

        $renderer = $container->getDefinition(self::RENDERER);
        $class = $renderer->getClass() ?? self::RENDERER;

        if (!is_a($class, HtmlErrorRenderer::class, true)) {
            return;
        }

        $renderer->addMethodCall('setNonce', [self::closure('scriptNonce'), self::closure('styleNonce')]);
    }

    private static function closure(string $method): Definition
    {
        return new Definition(Closure::class)
            ->setFactory([Closure::class, 'fromCallable'])
            ->setArguments([[new Reference(ContentSecurityPolicyContext::class), $method]]);
    }
}

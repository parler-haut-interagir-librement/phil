<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Closure;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Infrastructure\ErrorHandler\ErrorPageNoncePass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ErrorPageNoncePass::class)]
final class ErrorPageNoncePassTest extends TestCase
{
    public function testTheHtmlErrorRendererGetsBothNonces(): void
    {
        $container = $this->container(HtmlErrorRenderer::class);

        new ErrorPageNoncePass()->process($container);

        $calls = $container->getDefinition(ErrorPageNoncePass::RENDERER)->getMethodCalls();
        self::assertCount(1, $calls);
        $call = $calls[0];
        self::assertIsArray($call);
        self::assertSame('setNonce', $call[0] ?? null);
        $arguments = $call[1] ?? null;
        self::assertIsArray($arguments);

        foreach (['scriptNonce', 'styleNonce'] as $i => $method) {
            $closure = $arguments[$i] ?? null;
            self::assertInstanceOf(Definition::class, $closure);
            self::assertSame(Closure::class, $closure->getClass());
            self::assertSame([Closure::class, 'fromCallable'], $closure->getFactory());
            $callable = $closure->getArgument(0);
            self::assertIsArray($callable);
            self::assertEquals(new Reference(ContentSecurityPolicyContext::class), $callable[0]);
            self::assertSame($method, $callable[1]);
        }
    }

    public function testTheRendererReadsTheNoncesWhenItRenders(): void
    {
        $container = $this->container(HtmlErrorRenderer::class);
        $container->register('request_stack', RequestStack::class)->setPublic(true);
        $container->getDefinition(ContentSecurityPolicyContext::class)->setArguments([new Reference('request_stack')]);
        $container->getDefinition(ErrorPageNoncePass::RENDERER)->setPublic(true);
        new ErrorPageNoncePass()->process($container);
        $container->compile(true);

        self::assertInstanceOf(HtmlErrorRenderer::class, $container->get(ErrorPageNoncePass::RENDERER));
    }

    public function testAnotherRendererOrNoPolicyIsLeftAlone(): void
    {
        $other = $this->container(stdClass::class);
        new ErrorPageNoncePass()->process($other);
        self::assertSame([], $other->getDefinition(ErrorPageNoncePass::RENDERER)->getMethodCalls());

        $noPolicy = $this->container(HtmlErrorRenderer::class);
        $noPolicy->removeDefinition(ContentSecurityPolicyContext::class);
        new ErrorPageNoncePass()->process($noPolicy);
        self::assertSame([], $noPolicy->getDefinition(ErrorPageNoncePass::RENDERER)->getMethodCalls());

        $noRenderer = new ContainerBuilder();
        $noRenderer->register(ContentSecurityPolicyContext::class, ContentSecurityPolicyContext::class);
        new ErrorPageNoncePass()->process($noRenderer);
        self::assertFalse($noRenderer->hasDefinition(ErrorPageNoncePass::RENDERER));
    }

    private function container(string $rendererClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ErrorPageNoncePass::RENDERER, $rendererClass);
        $container->register(ContentSecurityPolicyContext::class, ContentSecurityPolicyContext::class);

        return $container;
    }
}

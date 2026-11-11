<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Tests\Unit;

use Phil\TypographyBundle\Infrastructure\DependencyInjection\TypographyServices;
use Phil\TypographyBundle\Infrastructure\DependencyInjection\UjoFilterPass;
use Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter;
use Phil\Ujo\Html\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(UjoFilterPass::class)]
final class UjoFilterPassTest extends TestCase
{
    public function testTheRendererIsDecorated(): void
    {
        $container = new ContainerBuilder();
        $container->register(HtmlRenderer::class, HtmlRenderer::class);
        $container->register(TypographyServices::UJO_FILTER, TypographyHtmlFilter::class);

        new UjoFilterPass()->process($container);
        $decorator = $container->getDefinition(TypographyServices::UJO_RENDERER);

        self::assertSame(HtmlRenderer::class, $decorator->getClass());
        self::assertSame([HtmlRenderer::class, null, 0], $decorator->getDecoratedService());
        self::assertEquals([new Reference(TypographyServices::UJO_RENDERER.'.inner'), 'withFilter'], $decorator->getFactory());
        self::assertEquals([new Reference(TypographyServices::UJO_FILTER)], $decorator->getArguments());
        self::assertTrue($container->hasDefinition(TypographyServices::UJO_FILTER));
    }

    public function testWithoutARendererTheFilterGoes(): void
    {
        $container = new ContainerBuilder();
        $container->register(TypographyServices::UJO_FILTER, TypographyHtmlFilter::class);

        new UjoFilterPass()->process($container);

        self::assertFalse($container->hasDefinition(TypographyServices::UJO_FILTER));
        self::assertFalse($container->hasDefinition(TypographyServices::UJO_RENDERER));
    }

    public function testWithoutAFilterNothingChanges(): void
    {
        $container = new ContainerBuilder();
        $container->register(HtmlRenderer::class, HtmlRenderer::class);

        new UjoFilterPass()->process($container);

        self::assertFalse($container->hasDefinition(TypographyServices::UJO_RENDERER));
    }
}

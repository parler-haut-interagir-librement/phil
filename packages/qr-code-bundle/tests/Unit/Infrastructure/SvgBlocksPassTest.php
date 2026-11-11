<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Phil\QrCodeBundle\Infrastructure\DependencyInjection\SvgBlocksPass;
use Phil\QrCodeBundle\Infrastructure\Ujo\QrCodeBlockRenderer;
use Phil\SvgBlocks\BlockMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SvgBlocksPass::class)]
final class SvgBlocksPassTest extends TestCase
{
    public function testReplacesTheQrCodeRendererOfTheBlocks(): void
    {
        $container = new ContainerBuilder();
        $container->register(QrCodeBlockRenderer::class);

        $chart = new Definition('Chart');
        $container->register(BlockMarkup::class)->setArguments(['signer', ['qrCode' => new Definition('Old'), 'chart' => $chart], []]);
        new SvgBlocksPass()->process($container);

        self::assertEquals(['qrCode' => new Reference(QrCodeBlockRenderer::class), 'chart' => $chart], $container->getDefinition(BlockMarkup::class)->getArgument(1));
    }

    public function testLeavesAContainerWithoutTheBlocksOrTheRenderer(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockMarkup::class)->setArguments(['signer', ['qrCode' => 'old'], []]);
        new SvgBlocksPass()->process($container);
        self::assertSame(['qrCode' => 'old'], $container->getDefinition(BlockMarkup::class)->getArgument(1));

        $container = new ContainerBuilder();
        $container->register(QrCodeBlockRenderer::class);
        new SvgBlocksPass()->process($container);
        self::assertFalse($container->hasDefinition(BlockMarkup::class));
    }

    public function testLeavesRenderersThatAreNoList(): void
    {
        $container = new ContainerBuilder();
        $container->register(QrCodeBlockRenderer::class);
        $container->register(BlockMarkup::class)->setArguments(['signer', new Reference('renderers'), []]);
        new SvgBlocksPass()->process($container);

        self::assertEquals(new Reference('renderers'), $container->getDefinition(BlockMarkup::class)->getArgument(1));
    }
}

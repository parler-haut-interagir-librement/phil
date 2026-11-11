<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit;

use Phil\PagedMedia\Pdf\Document;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCodeBundle\Application\QrCodeDefaults;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\DependencyInjection\QrCodeServices;
use Phil\QrCodeBundle\Infrastructure\DependencyInjection\SvgBlocksPass;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeController;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUrls;
use Phil\QrCodeBundle\Infrastructure\PagedMedia\PdfQrCodes;
use Phil\QrCodeBundle\Infrastructure\Twig\Components\QrCodeComponent;
use Phil\QrCodeBundle\Infrastructure\Twig\QrCodeExtension;
use Phil\QrCodeBundle\Infrastructure\Ujo\QrCodeBlockRenderer;
use Phil\QrCodeBundle\PhilQrCodeBundle;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilQrCodeBundle::class)]
#[CoversClass(QrCodeServices::class)]
final class PhilQrCodeBundleTest extends TestCase
{
    public function testRegistersTheServicesOfTheDefaults(): void
    {
        $container = $this->container([]);
        $codes = $container->getDefinition(QrCodes::class);
        $defaults = $codes->getArgument(0);

        self::assertTrue($codes->isPublic());
        self::assertInstanceOf(Definition::class, $defaults);
        self::assertSame([4, '#000000', '#ffffff', 0, 8, true, true, 2048], \array_slice($defaults->getArguments(), 1));
        self::assertFalse($container->hasDefinition(QrCodeController::class));
        self::assertFalse($container->hasDefinition(QrCodeUrls::class));
        self::assertNull($container->getDefinition(QrCodeExtension::class)->getArgument(1));
        self::assertTrue($container->getDefinition(QrCodeExtension::class)->hasTag('twig.extension'));
        self::assertSame([['key' => 'PhilQrCode:QrCode', 'template' => '@PhilQrCode/components/QrCode.html.twig']], $container->getDefinition(QrCodeComponent::class)->getTag('twig.component'));
        self::assertFalse($container->getDefinition(QrCodeComponent::class)->isShared());
        self::assertEquals([new Reference(QrCodes::class)], $container->getDefinition(QrCodeComponent::class)->getArguments());
        self::assertEquals([new Reference(QrCodes::class), null], $container->getDefinition(QrCodeExtension::class)->getArguments());
    }

    public function testRegistersTheRendererOfTheBlocksWhenPhilSvgBlocksIsInstalled(): void
    {
        if (!interface_exists(MarkupRenderer::class)) {
            self::markTestSkipped('phil/svg-blocks is not installed: the qrCode block of UJO documents is an optional integration.');
        }

        $container = $this->container([]);

        self::assertTrue($container->getDefinition(QrCodeBlockRenderer::class)->isPublic());
        self::assertEquals([new Reference(QrCodes::class)], $container->getDefinition(QrCodeBlockRenderer::class)->getArguments());
    }

    public function testRegistersThePdfDrawingWhenPhilPagedMediaIsInstalled(): void
    {
        if (!class_exists(Document::class)) {
            self::markTestSkipped('phil/paged-media is not installed: QR codes drawn in PDF documents are an optional integration.');
        }

        $container = $this->container([]);

        self::assertTrue($container->getDefinition(PdfQrCodes::class)->isPublic());
        self::assertEquals([new Reference(QrCodes::class)], $container->getDefinition(PdfQrCodes::class)->getArguments());
    }

    public function testRegistersTheRouteAndTheConfiguredValues(): void
    {
        $container = $this->container(['error_correction' => 'H', 'quiet_zone' => 2, 'dark' => '#112233', 'light' => '#FFEEDD', 'size' => 300, 'png_scale' => 4, 'eci' => false, 'kanji' => false, 'max_bytes' => 100, 'logos' => ['brand' => ['path' => '/l.svg']], 'route' => ['enabled' => true, 'cache_max_age' => 60]]);
        $defaults = $container->getDefinition(QrCodes::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $defaults);
        $level = $defaults->getArgument(0);
        self::assertInstanceOf(Definition::class, $level);
        $logos = $container->getDefinition(QrCodes::class)->getArgument(1);
        self::assertInstanceOf(Definition::class, $logos);

        self::assertSame(['H'], $level->getArguments());
        self::assertSame([ErrorCorrection::class, 'from'], $level->getFactory());
        self::assertSame(QrCodeDefaults::class, $defaults->getClass());
        self::assertSame([2, '#112233', '#FFEEDD', 300, 4, false, false, 100], \array_slice($defaults->getArguments(), 1));
        self::assertSame([['brand' => ['path' => '/l.svg', 'zone' => 0.2]]], $logos->getArguments());
        self::assertEquals([new Reference(QrCodes::class), new Reference(QrCodeServices::SIGNER), 60], $container->getDefinition(QrCodeController::class)->getArguments());
        self::assertTrue($container->getDefinition(QrCodeController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(QrCodeController::class)->isPublic());
        self::assertSame(['%kernel.secret%', '_qr_signature'], $container->getDefinition(QrCodeServices::SIGNER)->getArguments());
        self::assertEquals([new Reference('router'), new Reference(QrCodeServices::SIGNER), new Reference(QrCodes::class)], $container->getDefinition(QrCodeUrls::class)->getArguments());
        self::assertEquals(new Reference(QrCodeUrls::class), $container->getDefinition(QrCodeExtension::class)->getArgument(1));
        self::assertTrue($container->getDefinition(QrCodeUrls::class)->isPublic());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refused(): iterable
    {
        yield 'a level' => [['error_correction' => 'X']];
        yield 'a quiet zone' => [['quiet_zone' => 17]];
        yield 'a dark colour' => [['dark' => 'black']];
        yield 'a light colour' => [['light' => '#fff']];
        yield 'a colour not a text' => [['dark' => 1]];
        yield 'a size' => [['size' => 4097]];
        yield 'a scale' => [['png_scale' => 0]];
        yield 'a scale too large' => [['png_scale' => 33]];
        yield 'a negative quiet zone' => [['quiet_zone' => -1]];
        yield 'a negative size' => [['size' => -1]];
        yield 'no payload' => [['max_bytes' => 0]];
        yield 'a payload' => [['max_bytes' => 7090]];
        yield 'a logo without a path' => [['logos' => ['brand' => ['zone' => 0.2]]]];
        yield 'a logo zone too large' => [['logos' => ['brand' => ['path' => '/l.svg', 'zone' => 0.31]]]];
        yield 'a logo zone too small' => [['logos' => ['brand' => ['path' => '/l.svg', 'zone' => 0.04]]]];
        yield 'a cache age' => [['route' => ['cache_max_age' => -1]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testRefusesAConfigurationOutOfRange(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->container($config);
    }

    public function testAcceptsTheBoundsOfTheConfiguration(): void
    {
        $container = $this->container(['quiet_zone' => 16, 'size' => 4096, 'png_scale' => 32, 'max_bytes' => 7089, 'logos' => ['a' => ['path' => '/a', 'zone' => 0.3], 'b' => ['path' => '/b', 'zone' => 0.05]]]);
        self::assertTrue($container->hasDefinition(QrCodes::class));

        $low = $this->container(['error_correction' => 'L', 'quiet_zone' => 0, 'size' => 0, 'png_scale' => 1, 'max_bytes' => 1, 'route' => ['enabled' => true, 'cache_max_age' => 0]]);
        $defaults = $low->getDefinition(QrCodes::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $defaults);
        $level = $defaults->getArgument(0);
        self::assertInstanceOf(Definition::class, $level);
        self::assertSame(['L'], $level->getArguments());
        self::assertSame([0, '#000000', '#ffffff', 0, 1, true, true, 1], \array_slice($defaults->getArguments(), 1));
        self::assertSame(0, $low->getDefinition(QrCodeController::class)->getArgument(2));
        self::assertSame(31_536_000, $this->container(['route' => ['enabled' => true]])->getDefinition(QrCodeController::class)->getArgument(2));
        self::assertSame('phil_qr_code', new PhilQrCodeBundle()->getContainerExtension()?->getAlias());
    }

    public function testAddsThePassOfTheBlocks(): void
    {
        $container = new ContainerBuilder();
        new PhilQrCodeBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof SvgBlocksPass));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = new PhilQrCodeBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}

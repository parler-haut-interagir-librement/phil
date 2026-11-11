<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\DependencyInjection;

use Phil\PagedMedia\Pdf\Document;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCodeBundle\Application\Logos;
use Phil\QrCodeBundle\Application\QrCodeDefaults;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeController;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUrls;
use Phil\QrCodeBundle\Infrastructure\PagedMedia\PdfQrCodes;
use Phil\QrCodeBundle\Infrastructure\Twig\Components\QrCodeComponent;
use Phil\QrCodeBundle\Infrastructure\Twig\QrCodeExtension;
use Phil\QrCodeBundle\Infrastructure\Ujo\QrCodeBlockRenderer;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The services of the configuration: `QrCodes` with its defaults and logos, the Twig extension, the component when
 * Symfony UX is there, the signed route when it is enabled, the renderer of UJO blocks when phil/svg-blocks is there,
 * and the PDF drawing when phil/paged-media is there.
 */
final class QrCodeServices
{
    /** The signer of the route's addresses. */
    public const string SIGNER = 'phil_qr_code.uri_signer';

    /**
     * @param array{error_correction: string, quiet_zone: int, dark: string, light: string, size: int, png_scale: int, eci: bool, kanji: bool, max_bytes: int, logos: array<string, array{path: string, zone: float}>, route: array{enabled: bool, cache_max_age: int}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $defaults = new Definition(QrCodeDefaults::class, [
            new Definition(ErrorCorrection::class, [$config['error_correction']])->setFactory([ErrorCorrection::class, 'from']),
            $config['quiet_zone'],
            $config['dark'],
            $config['light'],
            $config['size'],
            $config['png_scale'],
            $config['eci'],
            $config['kanji'],
            $config['max_bytes'],
        ]);
        $container->register(QrCodes::class, QrCodes::class)
            ->setArguments([$defaults, new Definition(Logos::class, [$config['logos']])])
            ->setPublic(true);

        $urls = null;

        if ($config['route']['enabled']) {
            $container->register(self::SIGNER, UriSigner::class)->setArguments(['%kernel.secret%', '_qr_signature']);
            $container->register(QrCodeUrls::class, QrCodeUrls::class)
                ->setArguments([new Reference('router'), new Reference(self::SIGNER), new Reference(QrCodes::class)])
                ->setPublic(true);
            $container->register(QrCodeController::class, QrCodeController::class)
                ->setArguments([new Reference(QrCodes::class), new Reference(self::SIGNER), $config['route']['cache_max_age']])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
            $urls = new Reference(QrCodeUrls::class);
        }

        $container->register(QrCodeExtension::class, QrCodeExtension::class)
            ->setArguments([new Reference(QrCodes::class), $urls])
            ->addTag('twig.extension');

        if (class_exists(AsTwigComponent::class)) {
            $container->register(QrCodeComponent::class, QrCodeComponent::class)
                ->setArguments([new Reference(QrCodes::class)])
                ->setShared(false)
                ->addTag('twig.component', ['key' => 'PhilQrCode:QrCode', 'template' => '@PhilQrCode/components/QrCode.html.twig']);
        }

        if (interface_exists(MarkupRenderer::class)) {
            $container->register(QrCodeBlockRenderer::class, QrCodeBlockRenderer::class)
                ->setArguments([new Reference(QrCodes::class)])
                ->setPublic(true);
        }

        if (class_exists(Document::class)) {
            $container->register(PdfQrCodes::class, PdfQrCodes::class)
                ->setArguments([new Reference(QrCodes::class)])
                ->setPublic(true);
        }
    }
}

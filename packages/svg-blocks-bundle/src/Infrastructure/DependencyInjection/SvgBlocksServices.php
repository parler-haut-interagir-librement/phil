<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\DependencyInjection;

use Closure;
use Phil\LatexMathMl\LatexToMathMl;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\SvgWriter;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Http\HttpMermaidService;
use Phil\SvgBlocks\Renderer\ChartRenderer;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Renderer\MermaidRenderer;
use Phil\SvgBlocks\Renderer\QrCodeRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocksBundle\Application\Handler\RenderDeferredMarkupHandler;
use Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup;
use Phil\SvgBlocksBundle\Infrastructure\Form\StoredMarkupTypeExtension;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewRequest;
use Phil\SvgBlocksBundle\Infrastructure\Messenger\MarkupScheduler;
use Phil\SvgChart\SvgChartWriter;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The services of the configuration: the signer, a renderer per drawn type, `BlockMarkup`, the closure that the UJO
 * renderer checks keys with, the form extension, the preview endpoint, and the background pass of the diagrams.
 */
final class SvgBlocksServices
{
    /** The closure from a block, its markup and its key to whether this server wrote them. */
    public const string TRUSTS = 'phil_svg_blocks.trusts';

    /** The UJO renderer, decorated to draw the stored markup. */
    public const string UJO_RENDERER = 'phil_svg_blocks.ujo_renderer';

    /** The service, a MarkupDocuments, behind the background pass; a parameter until `StoredMarkupPass` reads it. */
    public const string DOCUMENTS = 'phil_svg_blocks.documents';

    /**
     * @param array{secret: string, qr_code: array{error_correction: string}, chart: array{width: int, height: int}, math: array{max_length: int, max_depth: int, max_expansions: int}, mermaid: array{url: ?string, timeout: float, async: bool, documents: ?string}, preview: array{enabled: bool, max_bytes: int}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(Signer::class, Signer::class)->setArguments([$config['secret']]);
        $container->register(self::TRUSTS, Closure::class)
            ->setFactory([Closure::class, 'fromCallable'])
            ->setArguments([[new Reference(Signer::class), 'trusts']]);

        $renderers = [
            'qrCode' => new Definition(QrCodeRenderer::class, [
                new Definition(SvgWriter::class),
                new Definition(ErrorCorrection::class, [$config['qr_code']['error_correction']])->setFactory([ErrorCorrection::class, 'from']),
            ]),
            'chart' => new Definition(ChartRenderer::class, [new Definition(SvgChartWriter::class, [$config['chart']['width'], $config['chart']['height']])]),
            'math' => new Definition(MathRenderer::class, [new Definition(LatexToMathMl::class, [$config['math']['max_length'], $config['math']['max_depth'], $config['math']['max_expansions']])]),
        ];

        if (null !== $config['mermaid']['url']) {
            $container->register(HttpMermaidService::class, HttpMermaidService::class)
                ->setArguments([new Reference('http_client'), $config['mermaid']['url'], $config['mermaid']['timeout']]);
            // The `<style>` of a diagram is kept, its rules rewritten by phil/css-sanitizer: Mermaid draws with it.
            $renderers['mermaid'] = new Definition(MermaidRenderer::class, [
                new Reference(HttpMermaidService::class),
                new Definition(SvgSanitizer::class, [new Definition(Policy::class, ['$styleElements' => true])]),
            ]);
        }

        $container->register(BlockMarkup::class, BlockMarkup::class)
            ->setArguments([new Reference(Signer::class), $renderers, $config['mermaid']['async'] ? ['mermaid'] : []])
            ->setPublic(true);

        if (class_exists(UjoType::class)) {
            $container->register(StoredMarkupTypeExtension::class, StoredMarkupTypeExtension::class)
                ->setArguments([new Reference(BlockMarkup::class), new Reference(Ujo::class)])
                ->addTag('form.type_extension');
        }

        if ($config['preview']['enabled'] && interface_exists(CsrfTokenManagerInterface::class)) {
            $container->register(PreviewRequest::class, PreviewRequest::class)
                ->setArguments([new Reference('security.csrf.token_manager')]);
            $container->register(PreviewController::class, PreviewController::class)
                ->setArguments([new Reference(BlockMarkup::class), new Reference(PreviewRequest::class), $config['preview']['max_bytes']])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(MarkupScheduler::class, MarkupScheduler::class)
                ->setArguments([new Reference('messenger.default_bus')])
                ->setPublic(true);
            $container->setParameter(self::DOCUMENTS, $config['mermaid']['documents']);
            $container->register(RenderDeferredMarkupHandler::class, RenderDeferredMarkupHandler::class)
                ->setArguments([null, new Reference(BlockMarkup::class)])
                ->addTag('messenger.message_handler', ['handles' => RenderDeferredMarkup::class]);
        }
    }
}

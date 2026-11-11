<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Infrastructure\DependencyInjection;

use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * The services of the configuration: `TextDiff` with its bound of edits, and the Twig filters, which read documents
 * with the `Ujo` service and render their blocks with the `HtmlRenderer` service of `phil/ujo-bundle` when it is
 * installed, and with the defaults of `phil/ujo` otherwise; the HTML a template does not mark safe goes through the
 * configured sanitizer. `TextDiff` checks each block of a document difference with the `Normalizer` service of
 * `phil/ujo-bundle` before rendering it, so that the types the application reads are those it checks.
 */
final class TextDiffServices
{
    /** The AssetMapper namespace of the bundle's stylesheet. */
    public const string ASSET_NAMESPACE = 'phil-text-diff';

    /** The AssetMapper namespace of the bundle's Stimulus controller. */
    public const string CONTROLLER_NAMESPACE = '@phil/text-diff-bundle';

    /**
     * @param string|null $sanitizer the id of the HTML sanitizer of the values a comparison of HTML does not trust
     */
    public static function register(ContainerBuilder $container, int $maxEdits, Tokenizer $tokenizer, ?string $sanitizer = null): void
    {
        $container->register(TextDiff::class, TextDiff::class)
            ->setArguments([$maxEdits, new Reference(Normalizer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);

        if (class_exists(AsTwigFilter::class)) {
            $container->register(TextDiffExtension::class, TextDiffExtension::class)
                ->setArguments([
                    new Reference(TextDiff::class),
                    $tokenizer,
                    new Reference(Ujo::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(HtmlRenderer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    null === $sanitizer ? null : new Reference($sanitizer),
                ])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }
    }
}

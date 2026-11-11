<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\DependencyInjection;

use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Twig\UjoExtension;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * The parts of `phil/ujo`, each a service an application can replace, `Ujo` built from them, and the form field, the
 * validator and the Twig extension on it.
 */
final class UjoServices
{
    public const int MAX_BYTES = JsonDecoder::DEFAULT_MAX_BYTES;

    public const int MAX_DEPTH = Normalizer::DEFAULT_MAX_DEPTH;

    public const int MAX_BLOCKS = Normalizer::DEFAULT_MAX_BLOCKS;

    public const int MAX_HTML_LENGTH = Normalizer::DEFAULT_MAX_HTML_LENGTH;

    public const int MAX_RAW_LENGTH = Normalizer::DEFAULT_MAX_RAW_LENGTH;

    public static function register(
        ContainerBuilder $container,
        bool $allowRaw,
        int $maxBytes,
        int $maxDepth,
        int $maxBlocks = self::MAX_BLOCKS,
        int $maxHtmlLength = self::MAX_HTML_LENGTH,
        int $maxRawLength = self::MAX_RAW_LENGTH,
    ): void {
        $registry = new Definition(BlockTypeRegistry::class)->setFactory([BlockTypeRegistry::class, 'default']);

        if ($allowRaw) {
            $registry = new Definition(BlockTypeRegistry::class)
                ->setFactory([$registry, 'with'])
                ->setArguments([new Definition(BlockType::class)->setFactory([StandardTypes::class, 'raw'])]);
        }

        $container->setDefinition(BlockTypeRegistry::class, $registry);
        $container->register(Normalizer::class, Normalizer::class)
            ->setArguments([new Reference(BlockTypeRegistry::class), null, null, $maxDepth, $maxBlocks, $maxHtmlLength, $maxRawLength]);
        $container->register(HtmlRenderer::class, HtmlRenderer::class)->setFactory([HtmlRenderer::class, 'default']);
        $container->register(PlainText::class, PlainText::class);
        $container->register(JsonDecoder::class, JsonDecoder::class)->setArguments([$maxBytes, JsonDecoder::DEFAULT_MAX_DEPTH]);
        $container->register(Ujo::class, Ujo::class)
            ->setArguments([new Reference(Normalizer::class), new Reference(HtmlRenderer::class), new Reference(PlainText::class), new Reference(JsonDecoder::class)])
            ->setPublic(true);
        $container->register(UjoType::class, UjoType::class)
            ->setArguments([new Reference(Ujo::class)])
            ->addTag('form.type');
        $container->register(ValidUjoValidator::class, ValidUjoValidator::class)
            ->setArguments([new Reference(Ujo::class)])
            ->addTag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $container->register(UjoExtension::class, UjoExtension::class)
                ->setArguments([new Reference(Ujo::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }
    }
}

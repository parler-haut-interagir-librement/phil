<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Markup\MarkupFormat;
use Phil\Typography\Typographer;
use Phil\Typography\Typographers;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Completes the smart punctuation once every bundle is loaded. It takes the `Typographers` of `phil/typography-bundle`
 * when that bundle registers them, so the locales and fixers configured there serve here too, and builds its own
 * otherwise: the presets of the kernel's default locale, without hyphenation. When `phil/typography-bundle` already
 * fixes the HTML of the UJO renderer, the formats rendered through UJO are left to it, and only HTML and plain text
 * are fixed here.
 */
final readonly class SmartPunctuationPass implements CompilerPassInterface
{
    /** The filter `phil/typography-bundle` runs on the HTML of the UJO renderer. */
    public const string TYPOGRAPHY_UJO_FILTER = 'phil_typography.ujo_filter';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MarkupServices::SMART_PUNCTUATION)) {
            return;
        }

        $definition = $container->getDefinition(MarkupServices::SMART_PUNCTUATION);
        $typographers = Typographers::class;

        if (!$container->hasDefinition($typographers)) {
            $typographers = MarkupServices::TYPOGRAPHERS;
            $container->register($typographers, Typographers::class)
                ->setArguments(['%kernel.default_locale%', [], Typographer::PROTECTED_TAGS, false]);
        }

        $definition->replaceArgument(1, new Reference($typographers));

        if ($container->hasDefinition(self::TYPOGRAPHY_UJO_FILTER)) {
            /** @var list<string> $formats */
            $formats = $definition->getArgument(2);
            $definition->replaceArgument(2, array_values(array_intersect($formats, [MarkupFormat::Html->value, MarkupFormat::Text->value])));
        }
    }
}

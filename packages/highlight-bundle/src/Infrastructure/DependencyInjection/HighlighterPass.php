<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Highlight\Highlighter;
use Phil\Highlight\Language;
use Phil\HighlightBundle\Infrastructure\Language\AliasedLanguage;
use Phil\Ujo\Html\HtmlRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Completes the highlighter once every bundle is loaded: the languages tagged `phil_highlight.language`, in the order
 * their services are registered, each in place of any language of the same name or alias, then the configured aliases;
 * and decorates the `HtmlRenderer` of `phil/ujo-bundle`, so the code blocks of every UJO document are coloured. Without
 * that renderer, the code highlighter is removed.
 */
final class HighlighterPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Highlighter::class)) {
            return;
        }

        $highlighter = $container->getDefinition(Highlighter::class);

        foreach (array_keys($container->findTaggedServiceIds(HighlightServices::LANGUAGE_TAG)) as $id) {
            $highlighter = $this->with($highlighter, new Reference($id));
        }

        /** @var array<int|string, string> $aliases an alias of digits alone is an integer key */
        $aliases = $container->getParameter(HighlightServices::ALIASES);
        $grouped = [];

        foreach ($aliases as $alias => $name) {
            $grouped[$name][] = (string) $alias;
        }

        foreach ($grouped as $name => $names) {
            $language = new Definition(Language::class)->setFactory([$highlighter, 'language'])->setArguments([$name]);
            $highlighter = $this->with($highlighter, new Definition(AliasedLanguage::class, [$language, $names]));
        }

        $container->setDefinition(Highlighter::class, $highlighter->setPublic(true));
        $this->decorateRenderer($container);
    }

    private function with(Definition $highlighter, Definition|Reference $language): Definition
    {
        return new Definition(Highlighter::class)->setFactory([$highlighter, 'with'])->setArguments([$language]);
    }

    private function decorateRenderer(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(HighlightServices::CODE_HIGHLIGHTER)) {
            return;
        }

        if (!$container->hasDefinition(HtmlRenderer::class)) {
            $container->removeDefinition(HighlightServices::CODE_HIGHLIGHTER);

            return;
        }

        $container->register(HighlightServices::UJO_RENDERER, HtmlRenderer::class)
            ->setFactory([new Reference(HighlightServices::UJO_RENDERER.'.inner'), 'withCodeHighlighter'])
            ->setArguments([new Reference(HighlightServices::CODE_HIGHLIGHTER)])
            ->setDecoratedService(HtmlRenderer::class);
    }
}

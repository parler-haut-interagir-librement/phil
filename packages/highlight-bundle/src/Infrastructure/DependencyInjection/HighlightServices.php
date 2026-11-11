<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Infrastructure\DependencyInjection;

use Closure;
use Phil\Highlight\Highlighter;
use Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * The services of the configuration: `Highlighter`, completed by `HighlighterPass` with the tagged languages and the
 * aliases; the code highlighter given to the UJO renderer; and the Twig filter.
 */
final class HighlightServices
{
    /** The tag of a `Language` service the highlighter reads, in place of a shipped one of the same name. */
    public const string LANGUAGE_TAG = 'phil_highlight.language';

    /** The closure from a code and its language to HTML, given to the code blocks of the UJO renderer. */
    public const string CODE_HIGHLIGHTER = 'phil_highlight.code_highlighter';

    /** The UJO renderer, decorated with the code highlighter. */
    public const string UJO_RENDERER = 'phil_highlight.ujo_renderer';

    /** The configured aliases, by alias, which `HighlighterPass` adds once the tagged languages are in. */
    public const string ALIASES = 'phil_highlight.aliases';

    /** The AssetMapper namespace of the bundle's stylesheets. */
    public const string ASSET_NAMESPACE = 'phil-highlight';

    /**
     * @param array<int|string, string> $aliases each alias, to the name of the language it stands for
     * @param bool                      $ujo     whether the code blocks of UJO documents are coloured
     */
    public static function register(ContainerBuilder $container, array $aliases, bool $ujo): void
    {
        $container->register(Highlighter::class, Highlighter::class)
            ->setFactory([Highlighter::class, 'default'])
            ->setPublic(true);
        $container->setParameter(self::ALIASES, $aliases);

        if ($ujo) {
            $container->register(self::CODE_HIGHLIGHTER, Closure::class)
                ->setFactory([Closure::class, 'fromCallable'])
                ->setArguments([[new Reference(Highlighter::class), 'highlight']]);
        }

        if (class_exists(AsTwigFilter::class)) {
            $container->register(HighlightExtension::class, HighlightExtension::class)
                ->setArguments([new Reference(Highlighter::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }
    }

    /**
     * Whether a value can name a language: what `Highlighter::with()` accepts.
     */
    public static function isName(mixed $name): bool
    {
        return \is_string($name) && 1 === preg_match('/^[a-z0-9_+#.-]{1,32}$/D', $name);
    }
}

<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The Twig functions of the bundle, served by `ContentRuntime`, which Twig builds only when a template calls
 * one of them.
 *
 * | Function | Returns |
 * |---|---|
 * | `content(reference, locale?)` | the live content of a root, normalized, or `null` |
 * | `content_url(reference, locale?, absolute?)` | the URL of a root's current route, or `null` |
 * | `content_link_url(link, locale?, absolute?)` | the URL of a normalized `link`: its URL, or its root's |
 * | `content_seo(content)` | the `<meta>` and `<link rel="canonical">` tags of a normalized content |
 * | `content_schema(content, type?, url?)` | a normalized content as schema.org data, to encode as JSON-LD |
 *
 * A reference is a `ContentReference`, its string form (`news:0192f3c4-…`), or a root. The locale defaults to
 * the request's.
 */
final class ContentExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('content', [ContentRuntime::class, 'content']),
            new TwigFunction('content_archive', [ContentRuntime::class, 'archive']),
            new TwigFunction('content_single', [ContentRuntime::class, 'single']),
            new TwigFunction('content_url', [ContentRuntime::class, 'url']),
            new TwigFunction('content_link_url', [ContentRuntime::class, 'linkUrl']),
            new TwigFunction('content_seo', [ContentRuntime::class, 'seo'], ['is_safe' => ['html']]),
            new TwigFunction('content_schema', [ContentRuntime::class, 'schema']),
        ];
    }
}

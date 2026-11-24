<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `phil_feed_links()`: the `<link rel="alternate">` of the feeds a page names, for its `<head>`.
 *
 * ```twig
 * {{ phil_feed_links() }}                     {# every feed, in every format served #}
 * {{ phil_feed_links('news') }}               {# one feed #}
 * {{ phil_feed_links(['news', 'events'], ['atom']) }}
 * ```
 */
final class FeedExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('phil_feed_links', [FeedRuntime::class, 'links'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\RobotsDirective;
use Phil\SeoCheck\Markup\RobotsDirectives;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Rendering;
use Phil\SeoCheck\Severity;

/**
 * The indexing directives of each rendering — `<meta name="robots">` and `X-Robots-Tag` — say what the page was
 * published as: a page published as indexable is not kept out of the index, and a page that is not says `noindex` to
 * every crawler. When the application serves the page in several channels, each rendering is held to what the
 * application says of its channel.
 *
 * A `noindex` addressed to one crawler only, on an indexable page, is a warning: it may be intended. A rendering that
 * answers with a redirect or an error is left to `http-status`.
 */
final readonly class Indexing implements SeoRule
{
    public const string NAME = 'indexing';

    public function __construct(
        private RobotsDirectives $directives = new RobotsDirectives(),
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        foreach ($page->renderings as $rendering) {
            $status = $rendering->page->status;

            if ($status >= 200 && $status < 300) {
                yield from $this->rendering($rendering, $page->isIndexableIn($rendering->page->channel));
            }
        }
    }

    /**
     * @return iterable<Finding>
     */
    private function rendering(Rendering $rendering, bool $indexable): iterable
    {
        $path = Excerpt::path($rendering->page->url);
        $channel = $rendering->page->channel;
        $forbidding = array_filter($this->directives->read($rendering), static fn (RobotsDirective $directive): bool => $directive->forbidsIndexing());

        if (!$indexable) {
            if (!array_any($forbidding, static fn (RobotsDirective $directive): bool => null === $directive->agent)) {
                yield new Finding(self::NAME, Severity::Error, null === $channel
                    ? new Message('indexing.not-blocked', ['path' => $path])
                    : new Message('indexing.not-blocked-in-channel', ['path' => $path, 'channel' => $channel]), $path);
            }

            return;
        }

        foreach ($forbidding as $directive) {
            $parameters = ['path' => $path, 'directive' => $directive->name, 'source' => $directive->source];

            yield match (true) {
                null !== $directive->agent => new Finding(self::NAME, Severity::Warning, new Message('indexing.blocked-for-agent', [...$parameters, 'agent' => $directive->agent]), $path),
                null !== $channel => new Finding(self::NAME, Severity::Error, new Message('indexing.blocked-in-channel', [...$parameters, 'channel' => $channel]), $path),
                default => new Finding(self::NAME, Severity::Error, new Message('indexing.blocked', $parameters), $path),
            };
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

use Phil\SeoCheck\Rendering;

/**
 * Reads the indexing directives of a rendering: the `<meta name="robots">` tags, those named after one crawler —
 * `<meta name="googlebot">` —, and the `X-Robots-Tag` headers, whose value may start with the crawler it is for:
 * `X-Robots-Tag: otherbot: noindex`.
 */
final readonly class RobotsDirectives
{
    /** The crawlers whose `<meta>` tags are read, by default. */
    public const array AGENTS = ['googlebot', 'googlebot-news', 'bingbot', 'msnbot', 'duckduckbot', 'yandex', 'baiduspider', 'applebot', 'slurp'];

    /** The directives that carry a value after a colon, which is then no crawler's name. */
    public const array VALUED = ['unavailable_after', 'max-snippet', 'max-image-preview', 'max-video-preview'];

    /** The most directives read from one rendering. */
    public const int MAX_DIRECTIVES = 200;

    /** @var list<string> */
    private array $agents;

    /**
     * @param list<string> $agents the crawlers whose `<meta>` tags are read, by name in any case
     */
    public function __construct(array $agents = self::AGENTS)
    {
        $this->agents = array_map(strtolower(...), $agents);
    }

    /**
     * @return list<RobotsDirective>
     */
    public function read(Rendering $rendering): array
    {
        $directives = [];

        foreach ($rendering->markup->meta as $name => $contents) {
            $agent = 'robots' === $name ? null : (\in_array($name, $this->agents, true) ? $name : false);

            if (false === $agent) {
                continue;
            }

            foreach ($contents as $content) {
                array_push($directives, ...$this->split($content, $agent, RobotsDirective::META));
            }
        }

        foreach ($rendering->page->header('X-Robots-Tag') as $value) {
            $agent = null;

            if (1 === preg_match('/^\s*([A-Za-z0-9_.-]+)\s*:(.*)/s', $value, $match) && !\in_array(strtolower($match[1]), self::VALUED, true)) {
                $agent = strtolower($match[1]);
                $value = $match[2];
            }

            array_push($directives, ...$this->split($value, $agent, RobotsDirective::HEADER));
        }

        return \array_slice($directives, 0, self::MAX_DIRECTIVES);
    }

    /**
     * @return list<RobotsDirective>
     */
    private function split(string $value, ?string $agent, string $source): array
    {
        $directives = [];

        foreach (explode(',', $value, self::MAX_DIRECTIVES) as $part) {
            $name = strtolower(trim(preg_replace('/:.*/s', '', $part) ?? ''));

            if ('' !== $name) {
                $directives[] = new RobotsDirective($name, $agent, $source);
            }
        }

        return $directives;
    }
}

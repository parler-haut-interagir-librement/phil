<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Visitor;

use Override;
use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;

/**
 * Classifies a visit from patterns known to name machines, read in this order:
 *
 * 1. a purpose of `prefetch`, `prerender` or `preview`, a token of the purpose headers: a prefetch;
 * 2. a `User-Agent` holding the name of a link preview — a messaging service, a social network —: a link preview;
 * 3. no `User-Agent`, or one holding a pattern of a crawler, a script or a monitor: a robot;
 * 4. anything else: a person.
 *
 * A pattern is a piece of text matched without case anywhere in the first 512 bytes of the `User-Agent`; the
 * application adds its own to the lists. Matching is plain search, never a regular expression: a request cannot make
 * it slow. A client that lies about itself is counted as what it claims.
 */
final readonly class KnownVisitorClassifier implements VisitorClassifier
{
    /** The bytes of a `User-Agent` read; a longer one is cut. */
    public const int MAX_USER_AGENT = 512;

    /** The bytes of the purpose headers read. */
    public const int MAX_PURPOSE = 256;

    /** The purposes of a request made ahead of a click. */
    public const array PREFETCH_PURPOSES = ['prefetch', 'prerender', 'preview'];

    /** The link previews of messaging services and social networks, read before the robots: their names hold "bot". */
    public const array LINK_PREVIEWS = [
        'facebookexternalhit', 'facebot', 'twitterbot', 'slackbot', 'slack-imgproxy', 'discordbot', 'telegrambot',
        'whatsapp', 'linkedinbot', 'skypeuripreview', 'microsoftpreview', 'redditbot', 'mastodon/', 'pleroma', 'misskey',
        'cardyb', 'snap url preview', 'pinterestbot', 'embedly', 'iframely', 'vkshare', 'kakaotalk-scrap', 'synapse',
        'mattermost-bot',
    ];

    /** Crawlers, scripts, monitors and headless browsers. */
    public const array ROBOTS = [
        'bot', 'crawl', 'spider', 'slurp', 'scrap', 'fetch', 'archiver', 'monitor', 'checker', 'preview', 'scanner',
        'validator', 'curl/', 'wget/', 'httpie', 'python', 'aiohttp', 'httpx', 'go-http-client', 'java/', 'okhttp',
        'apache-httpclient', 'libwww', 'lwp-', 'guzzle', 'symfony', 'axios', 'node-fetch', 'undici', 'got (', 'ruby',
        'perl', 'php/', 'headless', 'phantomjs', 'puppeteer', 'playwright', 'selenium', 'lighthouse', 'pingdom',
        'uptime', 'statuscake', 'site24x7', 'newrelic', 'datadog', 'mediapartners', 'apis-google', 'feedburner',
        'feedly', 'w3c_', 'ia_archiver', 'wordpress', 'nutch', 'chatgpt', 'claude', 'anthropic', 'perplexity', 'cohere',
    ];

    /** @var array<int, string> */
    private array $linkPreviews;

    /** @var array<int, string> */
    private array $robots;

    /**
     * @param list<string> $linkPreviews patterns of link previews added to the known ones
     * @param list<string> $robots       patterns of robots added to the known ones
     */
    public function __construct(array $linkPreviews = [], array $robots = [])
    {
        $this->linkPreviews = $this->patterns([...self::LINK_PREVIEWS, ...$linkPreviews]);
        $this->robots = $this->patterns([...self::ROBOTS, ...$robots]);
    }

    #[Override]
    public function classify(?string $userAgent, ?string $purpose): VisitorClass
    {
        if (null !== $purpose && $this->isAhead(strtolower(substr($purpose, 0, self::MAX_PURPOSE)))) {
            return VisitorClass::Prefetch;
        }

        $agent = strtolower(trim(substr($userAgent ?? '', 0, self::MAX_USER_AGENT)));

        return match (true) {
            '' === $agent => VisitorClass::Robot,
            $this->holds($agent, $this->linkPreviews) => VisitorClass::LinkPreview,
            $this->holds($agent, $this->robots) => VisitorClass::Robot,
            default => VisitorClass::Human,
        };
    }

    private function isAhead(string $purpose): bool
    {
        return [] !== array_intersect(explode(' ', strtr($purpose, ",;\t\r\n", '     ')), self::PREFETCH_PURPOSES);
    }

    /**
     * @param array<int, string> $patterns
     */
    private function holds(string $agent, array $patterns): bool
    {
        return array_any($patterns, static fn (string $pattern): bool => str_contains($agent, $pattern));
    }

    /**
     * The patterns lowercased and trimmed, without the empty ones, which every `User-Agent` would hold.
     *
     * @param list<string> $patterns
     *
     * @return array<int, string>
     */
    private function patterns(array $patterns): array
    {
        return array_filter(array_map(static fn (string $pattern): string => strtolower(trim($pattern)), $patterns), static fn (string $pattern): bool => '' !== $pattern);
    }
}

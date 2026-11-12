<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Benchmark;

use Generator;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;

/**
 * Every collection request classifies its referrer against the spam, search and social lists, and a real spam list
 * holds thousands of hosts: literal entries should cost a few hash lookups, `{}` entries one walk down a tree of labels.
 */
final class ReferrerClassifierBench
{
    /**
     * @var array<string, HostList>
     */
    private array $lists = [];

    /**
     * @var array<string, ReferrerClassifier>
     */
    private array $classifiers = [];

    /**
     * @var list<Origin>
     */
    private readonly array $own;

    public function __construct()
    {
        $search = HostList::of(['google.{}', 'bing.com', 'duckduckgo.com', 'yahoo.{}', 'yandex.{}', 'baidu.com', 'ecosia.org', 'qwant.com']);
        $social = HostList::of(['facebook.com', 'instagram.com', 't.co', 'linkedin.com', 'reddit.com', 'bsky.app', 'mastodon.social', 'youtube.com']);

        foreach ([10, 1_000, 5_000] as $size) {
            $this->lists['literal/'.$size] = HostList::of($this->spamHosts($size, 'com'));
            $this->classifiers['literal/'.$size] = new ReferrerClassifier($search, $social, $this->lists['literal/'.$size]);
        }

        foreach ([10, 100, 300, 2_000] as $size) {
            $this->lists['templated/'.$size] = HostList::of($this->spamHosts($size, '{}'));
        }

        $this->own = [Origin::fromString('https://example.com'), Origin::fromString('https://shop.example.com')];
    }

    /**
     * @ParamProviders({"listSizes", "hosts"})
     *
     * @param array{size: int, host: string} $params
     */
    public function benchLiteralListMatches(array $params): void
    {
        $this->lists['literal/'.$params['size']]->matches($params['host']);
    }

    /**
     * @ParamProviders({"templatedListSizes", "hosts"})
     *
     * @param array{size: int, host: string} $params
     */
    public function benchTemplatedListMatches(array $params): void
    {
        $this->lists['templated/'.$params['size']]->matches($params['host']);
    }

    /**
     * @ParamProviders({"listSizes", "referrers"})
     *
     * @param array{size: int, referer: string} $params
     */
    public function benchClassify(array $params): void
    {
        $this->classifiers['literal/'.$params['size']]->classify($params['referer'], $this->own);
    }

    /**
     * @return Generator<string, array{size: int}>
     */
    public function listSizes(): Generator
    {
        yield '10 entries' => ['size' => 10];
        yield '1000 entries' => ['size' => 1_000];
        yield '5000 entries' => ['size' => 5_000];
    }

    /**
     * @return Generator<string, array{size: int}>
     */
    public function templatedListSizes(): Generator
    {
        yield '10 entries' => ['size' => 10];
        yield '100 entries' => ['size' => 100];
        yield '300 entries' => ['size' => 300];
        yield '2000 entries' => ['size' => 2_000];
    }

    /**
     * @return Generator<string, array{host: string}>
     */
    public function hosts(): Generator
    {
        yield 'miss' => ['host' => 'www.some-blog.example.org'];
        yield 'deep hit' => ['host' => 'a.b.c.spam-site-0007.com'];
    }

    /**
     * @return Generator<string, array{referer: string}>
     */
    public function referrers(): Generator
    {
        yield 'website' => ['referer' => 'https://www.some-blog.example.org/2026/11/post'];
        yield 'search' => ['referer' => 'https://www.google.co.uk/search?q=events'];
    }

    /**
     * @return list<string>
     */
    private function spamHosts(int $count, string $suffix): array
    {
        return array_map(static fn (int $index): string => \sprintf('spam-site-%04d.%s', $index, $suffix), range(1, $count));
    }
}

<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Benchmark;

use Generator;
use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;

/**
 * Every visit of a short link is classified before its redirect: a person's browser is the slowest answer, read against
 * every pattern, and the longest `User-Agent` is cut at its first 512 bytes.
 */
final class KnownVisitorClassifierBench
{
    /** The last class answered, kept so that the call is not left unused. */
    public ?VisitorClass $class = null;

    private readonly KnownVisitorClassifier $classifier;

    public function __construct()
    {
        $this->classifier = new KnownVisitorClassifier();
    }

    /**
     * @param array{agent: string, purpose: ?string} $params
     *
     * @ParamProviders("visits")
     *
     * @Revs(1000)
     */
    public function benchClassify(array $params): void
    {
        $this->class = $this->classifier->classify($params['agent'], $params['purpose']);
    }

    /**
     * @return Generator<string, array{agent: string, purpose: ?string}>
     */
    public function visits(): Generator
    {
        yield 'a browser' => ['agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'purpose' => null];
        yield 'a prefetch' => ['agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', 'purpose' => 'prefetch;prerender'];
        yield 'a link preview' => ['agent' => 'facebookexternalhit/1.1 Facebot Twitterbot/1.0', 'purpose' => null];
        yield 'a robot' => ['agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'purpose' => null];
        yield 'a header of 8 KiB' => ['agent' => str_repeat('Mozilla/5.0 ', 700), 'purpose' => str_repeat('navigate ', 900)];
    }
}

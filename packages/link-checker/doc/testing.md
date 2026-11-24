# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. The requests go through phil/safe-http to Symfony's
`MockHttpClient`, with the host names pinned to public addresses of the documentation ranges, or to private ones to
test the refusals, so that no test resolves a name; the throttle sleeps on Symfony's `MockClock`, and the cache is an
`ArrayAdapter` on the same kind of clock, so that the lifetimes are tested without waiting.

- `tests/Support/ScriptedProbe` answers from a script, by address, and remembers what it was asked;
- `tests/Support/MemoryInternalPages` holds the pages of a site in memory;
- `tests/Support/RecordingThrottle` never waits, and remembers the hosts and the resets.

`tests/Unit/PipelineTest` runs the whole chain: two contents, their links extracted, deduplicated, paced, cached,
redirected, refused, and the anchors of an internal page read over HTTP. In the monorepo, Infection holds the package at
100 % of covered mutants.

No integration test reaches a live server: phil/safe-http refuses the loopback and private networks a local test server
listens on, and a public one would make the suite depend on the network.

## Testing an application's checks

An application tests its own `InternalPages`, or what it does with the reports, with a probe that answers from a script:

```php
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\Url\Url;

$probe = new class implements LinkProbe {
    public function probe(Url $url, bool $withBody = false): ProbeResult
    {
        return new ProbeResult(new LinkResult(str_ends_with($url->path, '/gone') ? LinkStatus::Dead : LinkStatus::Alive, 200));
    }
};

$report = new LinkChecker($probe, internalPages: $pages, internalHosts: ['www.example.com'])
    ->checkContent(new ContentLinks('1', Url::fromString('https://www.example.com/a'), '<a href="/gone">x</a>'));
```

Or with the real probe over a `MockHttpClient`, as the package's own tests do:

```php
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$probe = new HttpLinkProbe(
    new MockHttpClient([new MockResponse('', ['http_code' => 404])]),
    resolve: ['example.org' => '93.184.215.14'],
    throttle: new IntervalThrottle(1.0, new MockClock()),
);
```

## The fuzz target

`tests/Fuzz/extract.php` extracts the links of HTML nobody wrote with small limits, then indexes its anchors: nothing
throws, no more links than the limit come out, every link has either an address of the web or a status, never both,
and every address as written and every text stays on one line within its 200 characters. Its seeds are in
`tests/Fuzz/corpus/extract`.

```sh
composer fuzz -- extract
```

## The benchmark

`tests/Benchmark/ExtractBench.php` measures the work done before any request on a large article — 2,000 links, images
with `srcset`, 500 sections —: the links extracted and resolved, and the anchors of the page indexed.

```sh
composer bench
```

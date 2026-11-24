<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

use Generator;
use Phil\LinkChecker\Contract\InternalPages;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\Exception\InvalidOption;
use Phil\LinkChecker\Extraction\AnchorIndex;
use Phil\LinkChecker\Extraction\Link;
use Phil\LinkChecker\Extraction\LinkExtractor;
use Phil\LinkChecker\Report\CheckedLink;
use Phil\LinkChecker\Report\LinkReport;
use Phil\Url\Url;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Checks the links of contents and reports them, one `LinkReport` per content.
 *
 * Each address is checked once per batch, under the key of the normaliser, however many links and contents name it;
 * between batches, the cache keeps the results, a result to review for `$reviewTtl` seconds and the others for `$ttl`.
 * The probe asks the addresses themselves; only the address a link names is asked, never another page of its site.
 *
 * A link to the content's own page and its `#fragment` is looked for in the content. A link to one of the
 * `$internalHosts` — the hosts of the site itself — is read by `InternalPages` when the application can give the page
 * without a request; and when it carries a fragment, the element it names must exist in the target page, by its `id`
 * or the `name` of an `a`. Those pages are never cached: they are the site's own, and change with it.
 *
 * The checker keeps nothing between two calls; a batch is a generator, which a bundle or the application feeds with the
 * contents of one message, one page of a listing, or one content.
 */
final readonly class LinkChecker
{
    public const int MAX_TTL = 30 * 86_400;

    /** The results of each kind one batch keeps in memory, past which the oldest are forgotten. */
    private const int MAX_MEMO = 10_000;

    /** @var list<string> */
    private array $internalHosts;

    /**
     * @param list<string> $internalHosts the hosts of the site itself, in lowercase ASCII, `www.example.com`
     */
    public function __construct(
        private LinkProbe $probe,
        private LinkExtractor $extractor = new LinkExtractor(),
        private UrlNormalizer $normalizer = new UrlNormalizer(),
        private ?CacheInterface $cache = null,
        private ?InternalPages $internalPages = null,
        array $internalHosts = [],
        private int $ttl = 86_400,
        private int $reviewTtl = 3_600,
    ) {
        foreach ($internalHosts as $host) {
            if (1 !== preg_match('/^[a-z0-9.-]+$/D', $host)) {
                throw InvalidOption::host($host);
            }
        }

        if ($ttl < 1 || $ttl > self::MAX_TTL) {
            throw InvalidOption::outOfRange('The lifetime of a result', $ttl, 1, self::MAX_TTL);
        }

        if ($reviewTtl < 1 || $reviewTtl > $ttl) {
            throw InvalidOption::outOfRange('The lifetime of a result to review', $reviewTtl, 1, $ttl);
        }

        $this->internalHosts = $internalHosts;
    }

    public function checkContent(ContentLinks $content): LinkReport
    {
        foreach ($this->check([$content]) as $report) {
            return $report;
        }

        return new LinkReport($content->id, $content->page, []);
    }

    /**
     * @param iterable<ContentLinks> $contents
     *
     * @return Generator<int, LinkReport>
     */
    public function check(iterable $contents): Generator
    {
        $memo = new Memo(self::MAX_MEMO);

        foreach ($contents as $content) {
            $extracted = $this->extractor->extract($content->html, $content->page);
            $links = [];

            foreach ($extracted->links as $link) {
                $links[] = new CheckedLink($link, $this->result($link, $content, $memo));
            }

            yield new LinkReport($content->id, $content->page, $links, $extracted->truncated);
        }
    }

    private function result(Link $link, ContentLinks $content, Memo $memo): LinkResult
    {
        $url = $link->url;

        if (!$url instanceof Url) {
            return LinkStatus::Refused === $link->status ? new LinkResult(LinkStatus::Refused, reason: 'not a public address') : new LinkResult(LinkStatus::Invalid, reason: 'not a web address');
        }

        $fragment = $url->fragment;
        $key = $this->normalizer->key($url);

        if ($key === $this->normalizer->key($content->page)) {
            return $this->anchored(new LinkResult(LinkStatus::Alive), $fragment, $memo->remember('content', $content->id, fn (): AnchorIndex => AnchorIndex::fromHtml($content->html, $this->extractor->maxBytes)));
        }

        $page = $this->normalizer->withoutFragment($url);

        if (!\in_array($url->host, $this->internalHosts, true)) {
            return $memo->remember('external', $key, fn (): LinkResult => $this->cached($key, $page));
        }

        $internal = $memo->remember('internal', $key, fn (): ?AnchorIndex => $this->internalPage($page));

        if ($internal instanceof AnchorIndex) {
            return $this->anchored(new LinkResult(LinkStatus::Alive), $fragment, $internal);
        }

        if (null === $fragment) {
            return $memo->remember('probed', $key, fn (): LinkResult => $this->probe->probe($page)->result);
        }

        [$result, $anchors] = $memo->remember('anchored', $key, fn (): array => $this->probeWithBody($page));

        return $this->anchored($result, $fragment, $anchors);
    }

    /**
     * The anchors of a page of the site the application gives without a request; null when it cannot give it.
     */
    private function internalPage(Url $page): ?AnchorIndex
    {
        $html = $this->internalPages?->html($page);

        return null === $html ? null : AnchorIndex::fromHtml($html, $this->extractor->maxBytes);
    }

    /**
     * @return array{LinkResult, AnchorIndex|null}
     */
    private function probeWithBody(Url $page): array
    {
        $probed = $this->probe->probe($page, true);

        return [$probed->result, null === $probed->html ? null : AnchorIndex::fromHtml($probed->html, $this->extractor->maxBytes, $probed->complete)];
    }

    /**
     * The result of a page found, unless the element the fragment names is missing from it.
     */
    private function anchored(LinkResult $result, ?string $fragment, ?AnchorIndex $anchors): LinkResult
    {
        if (null === $fragment || !$anchors instanceof AnchorIndex || $anchors->has($fragment) || LinkStatus::Alive !== $result->status && !$result->status->isRedirect()) {
            return $result;
        }

        // The fragment of a Url is percent-encoded ASCII: a cut in bytes splits no character.
        return new LinkResult(LinkStatus::MissingAnchor, $result->code, $result->target, 'no element "'.substr($fragment, 0, 100).'" in the page');
    }

    private function cached(string $key, Url $url): LinkResult
    {
        if (!$this->cache instanceof CacheInterface) {
            return $this->probe->probe($url)->result;
        }

        $data = $this->cache->get('phil_link_checker.'.hash('xxh128', $key), function (ItemInterface $item) use ($url): array {
            $result = $this->probe->probe($url)->result;
            $item->expiresAfter(LinkStatus::Review === $result->status ? $this->reviewTtl : $this->ttl);

            return $result->toArray();
        });

        return LinkResult::fromArray($data) ?? $this->probe->probe($url)->result;
    }
}

<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Benchmark;

use Phil\LinkChecker\Extraction\AnchorIndex;
use Phil\LinkChecker\Extraction\LinkExtractor;
use Phil\Url\Url;

/**
 * The hot path of a check before any request: the links of a large article — 2,000 links, images with `srcset`,
 * paragraphs between them, about 600 KB — extracted and resolved, and the anchors of the same page indexed.
 */
final readonly class ExtractBench
{
    private string $html;

    private Url $page;

    private LinkExtractor $extractor;

    public function __construct()
    {
        $parts = ['<!doctype html><html><head><title>Article</title></head><body>'];

        for ($i = 1; $i <= 500; ++$i) {
            $parts[] = '<h2 id="section-'.$i.'">Section '.$i.'</h2><p>'.str_repeat('Lorem ipsum dolor sit amet. ', 30)
                .'<a href="/articles/'.$i.'">Article '.$i.'</a> and <a href="https://example.com/ref/'.$i.'?a=1&amp;b=2#note">a reference</a>.</p>'
                .'<img src="/media/'.$i.'.jpg" srcset="/media/'.$i.'-2x.jpg 2x" alt="Figure '.$i.'">';
        }

        $parts[] = '</body></html>';
        $this->html = implode('', $parts);
        $this->page = Url::fromString('https://site.example/blog/long-article');
        $this->extractor = new LinkExtractor();
    }

    public function benchExtractTheLinksOfALargeArticle(): void
    {
        $this->extractor->extract($this->html, $this->page);
    }

    public function benchIndexTheAnchorsOfALargeArticle(): bool
    {
        return AnchorIndex::fromHtml($this->html, $this->extractor->maxBytes)->has('section-250');
    }
}

<?php

declare(strict_types=1);

/*
 * The extraction of the links of a content, on HTML nobody wrote: `LinkExtractor::extract()` within small limits, then
 * the anchors of the same HTML. Neither throws; every link keeps its limits — at most the links allowed, an address that
 * is http or https or none at all with a status, words on one line within their characters —, and the anchors answer.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\LinkChecker\Extraction\AnchorIndex;
use Phil\LinkChecker\Extraction\Link;
use Phil\LinkChecker\Extraction\LinkExtractor;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$extractor = new LinkExtractor(8192, 64);
$page = Url::fromString('https://site.example/blog/post');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($extractor, $page): void {
    $extracted = $extractor->extract($input, $page);

    if (count($extracted->links) > $extractor->maxLinks) {
        throw new LogicException('More links than the limit.');
    }

    foreach ($extracted->links as $link) {
        if ((null === $link->url) === (null === $link->status)) {
            throw new LogicException('A link has both an address and a status, or neither.');
        }

        if (null !== $link->url && !in_array($link->url->scheme, ['http', 'https'], true)) {
            throw new LogicException('A link is not a web address.');
        }

        foreach ([$link->text, $link->href] as $text) {
            if (mb_strlen($text) > Link::MAX_TEXT || 1 === preg_match('/[\r\n\t]/', $text)) {
                throw new LogicException('Words out of their limits.');
            }
        }
    }

    AnchorIndex::fromHtml($input, 8192)->has('top');
});
$config->setMaxLen(8192);

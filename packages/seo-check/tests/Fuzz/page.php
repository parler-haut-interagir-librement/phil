<?php

declare(strict_types=1);

/*
 * The markup reader and the default rules on pages nobody wrote: whatever the page holds, the check returns a report
 * that encodes as JSON, and never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Render\JsonRenderer;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$checker = new SeoChecker();
$renderer = new JsonRenderer();
$reader = new MarkupReader(maxElements: 500);
$url = Url::fromString('https://example.org/fr/news/opening');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($checker, $renderer, $reader, $url): void {
    $page = CheckedPage::of($url, 'fr', true, [new RenderedPage($url, $input, 200, ['X-Robots-Tag' => substr($input, 0, 64)])], [], $reader);
    $renderer->render($checker->check($page));
});
$config->setMaxLen(4096);

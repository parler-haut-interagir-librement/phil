<?php

declare(strict_types=1);

/*
 * The sanitizer of a style sheet, under a policy that keeps nested rules, imports and image sets, on inputs nobody
 * wrote: whatever it reads, it never throws, and what it writes the same policy keeps whole.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\CssSanitizer\UrlKind;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$files = ['a.png' => UrlKind::Image, 'f.woff2' => UrlKind::Font, 's.css' => UrlKind::StyleSheet];
$rewriter = new CallbackRewriter(static fn (string $url, UrlKind $kind): ?string => ($files[$url] ?? null) === $kind ? $url : null);
$sanitizer = new CssSanitizer(new Policy()->withUrlRewriter($rewriter)->withAtRules(AtRuleKind::Import)->withMaxNesting(4));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($sanitizer): void {
    $once = $sanitizer->sanitize($input);
    $twice = $sanitizer->sanitize($once->css);

    if (!$twice->isClean() || $twice->css !== $once->css) {
        throw new LogicException('The sanitized style sheet does not read back the same: '.$once->css);
    }
});
$config->setMaxLen(4096);

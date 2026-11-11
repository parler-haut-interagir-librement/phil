<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/markup` measures: FrankenPHP keeps this script alive across requests, so the
 * Markup service built here serves every request, as one service of an application's worker does. Each request reads
 * a Markdown document of about 10 KB and prints its purified HTML.
 */

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());
$unit = "# Title\n\nSome *emphasis*, **strong**, `code`, [a link](https://example.com/a) and a note[^1].\n\n- one\n- two\n  - nested\n\n| a | b |\n|---|---|\n| c | d |\n\n> A quote\n\n```php\necho 1;\n```\n\n[^1]: The note.\n\n";
$source = str_repeat($unit, intdiv(10_000, strlen($unit)));

$page = static function () use ($markup, $source): void {
    echo $markup->toHtml($source, MarkupFormat::Markdown, Level::All);
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}

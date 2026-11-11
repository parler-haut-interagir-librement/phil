<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/text-diff` measures: FrankenPHP keeps this script alive across requests, so
 * the differ built here serves every request, as one service of an application's worker does. Each request compares
 * two versions of about 5 KB of text word by word, and two versions of about 5 KB of HTML, and prints both diffs.
 */

use Phil\TextDiff\TextDiff;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$differ = new TextDiff();
$before = str_repeat('The quick brown fox jumps over the lazy dog. ', 110);
$after = str_repeat('The quick red fox jumps over the sleepy dog. ', 55).str_repeat('The quick brown fox jumps over the lazy dog. ', 55);
$htmlBefore = str_repeat('<p>Hello <b>big</b> world, the quick brown fox.</p>', 100);
$htmlAfter = str_repeat('<p>Hello <i>small</i> world! The quick brown fox.</p>', 50).str_repeat('<p>Hello <b>big</b> world, the quick brown fox.</p>', 50);

$page = static function () use ($differ, $before, $after, $htmlBefore, $htmlAfter): void {
    echo $differ->toHtml($differ->words($before, $after));
    echo $differ->toHtml($differ->html($htmlBefore, $htmlAfter));
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}

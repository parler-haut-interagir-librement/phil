<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/typography` measures: FrankenPHP keeps this script alive across requests, so
 * the typographers built here, and the hyphenation patterns they load on first use, serve every request, as one
 * service of an application's worker does. Each request fixes about 10 KB of French HTML and 10 KB of English HTML.
 */

use Phil\Typography\Typographers;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$typographers = new Typographers();
$french = '<p>"Bonjour" , dit-il : l\'été arrive ! Les années 1914-1918... <em>vraiment</em> ?</p>';
$english = '<p>"Wait..." - she said, in 1914-1918. It\'s the hyphenation of extraordinary words.</p>';
$pages = [
    'fr' => str_repeat($french, intdiv(10_000, strlen($french))),
    'en' => str_repeat($english, intdiv(10_000, strlen($english))),
];

$page = static function () use ($typographers, $pages): void {
    foreach ($pages as $locale => $html) {
        echo $typographers->for($locale)->fix($html);
    }
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}

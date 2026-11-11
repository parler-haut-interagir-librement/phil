<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/highlight` measures: FrankenPHP keeps this script alive across requests, so
 * the highlighter built here, and the grammars it compiles on first use, serve every request, as one service of an
 * application's worker does. Each request highlights about 10 KB of PHP and 10 KB of JavaScript.
 */

use Phil\Highlight\Highlighter;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$highlighter = Highlighter::default();
$php = "<?php\n/** Doc */\nfinal class A { public function b(int \$c): string { return \"x{\$c}\" . 'y'; } }\n// comment\n";
$javascript = "const a = (b) => `x\${b}` + 'y'; /* c */ function d() { return /re+/g.test(a); }\n";
$sources = [
    'php' => str_repeat($php, intdiv(10_000, strlen($php))),
    'javascript' => str_repeat($javascript, intdiv(10_000, strlen($javascript))),
];

$page = static function () use ($highlighter, $sources): void {
    foreach ($sources as $language => $code) {
        echo '<pre><code>', $highlighter->toHtml($code, $language), '</code></pre>';
    }
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}

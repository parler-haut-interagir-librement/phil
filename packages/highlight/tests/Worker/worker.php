<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/highlight` measures: FrankenPHP keeps this script alive across requests, so
 * the highlighter built here, and the grammars it compiles on first use, serve every request, as one service of an
 * application's worker does. Each request highlights about 10 KB of PHP and 10 KB of JavaScript.
 *
 * At the end of each request, the page says it was served whole (`X-Phil-Bench: ok`) and how much memory the worker
 * holds (`X-Phil-Bench-Memory`, in bytes), so that the benchmark counts a broken page as failed and sees memory that
 * still grows after the warm-up. The page is buffered so that both headers go out after the work, before any byte.
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
    ob_start();
    foreach ($sources as $language => $code) {
        echo '<pre><code>', $highlighter->toHtml($code, $language), '</code></pre>';
    }

    header('X-Phil-Bench: ok');
    header('X-Phil-Bench-Memory: '.memory_get_usage());
    ob_end_flush();
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}

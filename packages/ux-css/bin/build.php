<?php

declare(strict_types=1);

/*
 * Writes dist/phil-ux.css, the single-file stylesheet, from css/phil-ux.css and the files it imports, and
 * dist/tokens.json, its tokens in the format of the Design Tokens Community Group, from css/tokens/:
 * `php bin/build.php` from the package, after a change under css/. The tests fail while either is stale.
 */

use Phil\UxCss\Stylesheet\StylesheetBundler;
use Phil\UxCss\Token\DesignTokens;
use Phil\UxCss\Token\TokenSheet;

$autoload = array_find(
    [__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php', __DIR__.'/../../../autoload.php'],
    static fn (string $path): bool => is_file($path),
);

if (null === $autoload) {
    fwrite(\STDERR, "Run composer install first.\n");

    exit(1);
}

require $autoload;

file_put_contents(__DIR__.'/../dist/phil-ux.css', StylesheetBundler::bundle(__DIR__.'/../css/phil-ux.css'));
echo "dist/phil-ux.css written.\n";

$tokens = DesignTokens::fromSheet(TokenSheet::fromFiles(...DesignTokens::sources(__DIR__.'/../css')));
file_put_contents(__DIR__.'/../dist/tokens.json', $tokens->toJson());
echo 'dist/tokens.json written; left out, their value having no type of the format: '.implode(', ', $tokens->skipped()).".\n";

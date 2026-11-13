<?php

declare(strict_types=1);

/*
 * Writes assets/styles/phil-ux-easyadmin.css, the theme's single stylesheet, from assets/src/phil-ux-easyadmin.css and
 * the token files of the installed phil/ux-css: `php bin/build.php` from the package, after a change of either. The
 * tests fail while the stylesheet is stale.
 */

use Phil\UxEasyAdminBundle\Infrastructure\Stylesheet\ThemeSheet;

$autoload = array_find(
    [__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php', __DIR__.'/../../../autoload.php'],
    static fn (string $path): bool => is_file($path),
);

if (null === $autoload) {
    fwrite(\STDERR, "Run composer install first.\n");

    exit(1);
}

require $autoload;

file_put_contents(__DIR__.'/../assets/styles/phil-ux-easyadmin.css', ThemeSheet::build(__DIR__.'/../assets/src/phil-ux-easyadmin.css', ThemeSheet::tokensDirectory()));
echo "assets/styles/phil-ux-easyadmin.css written.\n";

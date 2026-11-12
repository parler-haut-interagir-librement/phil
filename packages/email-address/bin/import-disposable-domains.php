<?php

declare(strict_types=1);

/*
 * Imports a list of disposable domains into data/:
 *
 *     php bin/import-disposable-domains.php path/to/disposable_email_blocklist.conf <release>
 *
 * The list is disposable_email_blocklist.conf of https://github.com/disposable-email-domains/disposable-email-domains,
 * dedicated to the public domain under CC0 1.0 Universal (data/LICENSE); the release is the commit or date imported.
 */

use Phil\EmailAddress\Data\DisposableDomainsImporter;

// The library's own vendor/, or the monorepo's while it is incubated there.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

$path = $argv[1] ?? null;
$release = $argv[2] ?? null;

if (null === $path || null === $release || !is_file($path)) {
    fwrite(\STDERR, "Usage: php bin/import-disposable-domains.php path/to/disposable_email_blocklist.conf <release>\n");

    exit(1);
}

printf("%d domains imported.\n", new DisposableDomainsImporter()->import((string) file_get_contents($path), __DIR__.'/../data', $release));

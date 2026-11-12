<?php

declare(strict_types=1);

/*
 * Imports the IANA list of top-level domains into data/:
 *
 *     php bin/import-top-level-domains.php path/to/tlds-alpha-by-domain.txt
 *
 * The list is https://data.iana.org/TLD/tlds-alpha-by-domain.txt, published by the Internet Assigned Numbers
 * Authority (data/LICENSE); its "# Version" line names the release recorded in data/VERSION.
 */

use Phil\EmailAddress\Data\TopLevelDomainsImporter;

// The library's own vendor/, or the monorepo's while it is incubated there.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

$path = $argv[1] ?? null;

if (null === $path || !is_file($path)) {
    fwrite(\STDERR, "Usage: php bin/import-top-level-domains.php path/to/tlds-alpha-by-domain.txt\n");

    exit(1);
}

printf("%d top-level domains imported.\n", new TopLevelDomainsImporter()->import((string) file_get_contents($path), __DIR__.'/../data'));

<?php

declare(strict_types=1);

/*
 * The reader of the Solr synonyms format, on input nobody wrote: whatever it reads, it returns a list of synonyms
 * or refuses it with InvalidSynonyms, and throws nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        new SolrSynonymsReader()->read($input, 'fr');
    } catch (InvalidSynonyms) {
    }
});
$config->setMaxLen(4096);

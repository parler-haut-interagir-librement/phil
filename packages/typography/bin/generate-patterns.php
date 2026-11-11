<?php

declare(strict_types=1);

/*
 * Writes the hyphenation patterns written for this library — those of tests/Generator/SyllableRules.php — into
 * data/hyphenation/:
 *
 *     php bin/generate-patterns.php
 *
 * The rules and the generator live with the tests, which check that the files are those the rules give.
 */

use Phil\Typography\Tests\Generator\PatternGenerator;
use Phil\Typography\Tests\Generator\SyllableRules;

// The library's own vendor/, or the monorepo's while it is incubated there; both map the tests' namespace.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

foreach (SyllableRules::all() as $tag => $rules) {
    $patterns = PatternGenerator::generate($rules);
    file_put_contents(__DIR__.'/../data/hyphenation/'.$tag.'.pat.txt', $patterns);
    printf("%s: %d patterns\n", $tag, substr_count($patterns, "\n"));
}

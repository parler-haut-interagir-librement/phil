<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        // The terms of the Verifiable Credentials and Data Integrity specifications.
        'words' => ['libsodium', 'Multikey', 'Multikeys', 'cryptosuite', 'cryptosuites', 'RDFC', 'Bitstring', 'multibase'],
    ],
    'deptrac' => [
        // The SVG baker reads and writes images through the DOM extension of PHP, which is outside the default.
        'ruleset' => [
            'PhilOpenBadges' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The canonicalization's equivalent mutants. The tests anchor it to its output on datasets only the n-degree
            // hashes tell apart, and it passes every evaluation test of the W3C RDFC-1.0 suite.
            // - A permutation whose path is already worse than the chosen one is skipped as soon as that is known: to
            //   skip it later, or to stop pruning it, or to prune it on an equal path, chooses the same path.
            'Continue_' => ['ignoreSourceCodeByRegex' => ['continue 2;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['continue 2;', '.*gzdecode\\(.*']],
            'GreaterThanOrEqualTo' => ['ignoreSourceCodeByRegex' => ["return '' !== \\\$chosen.*"]],
            'GreaterThanOrEqualToNegotiation' => ['ignoreSourceCodeByRegex' => ["return '' !== \\\$chosen.*"]],
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ["return '' !== \\\$chosen.*"]],
            'LogicalAndAllSubExprNegation' => ['ignoreSourceCodeByRegex' => ["return '' !== \\\$chosen.*"]],
            // - Two permutations that build the same path label blank nodes nothing tells apart: keeping the first or the
            //   last gives the same canonical form, and since a worse path never reaches the choice, keeping the last
            //   one that reaches it keeps the least.
            'LessThan' => ['ignoreSourceCodeByRegex' => ['.*strcmp\\(\\$path, \\$chosenPath\\) < 0.*']],
            'LogicalOrAllSubExprNegation' => ['ignoreSourceCodeByRegex' => ['.*strcmp\\(\\$path, \\$chosenPath\\) < 0.*']],
            // - A blank node already labelled canonically has a related hash of its own, so it is alone in its group:
            //   its path starts empty.
            'Assignment' => ['ignoreSourceCodeByRegex' => ['\\$path \\.= \'_:\'\\.\\$this->canonical->issue\\(\\$related\\);']],
            // - The first-degree hash is kept so as not to compute it again; computing it again gives the same.
            'AssignCoalesce' => ['ignoreSourceCodeByRegex' => ['.*firstDegree\\[\\$blank\\] \\?\\?=.*']],
            // The status list's bound on inflation only stops a bomb early: the length checked after it gives the same
            // refusal whatever the bound. (The gzdecode regex of DecrementInteger above.)
        ],
    ],
    'fuzz' => [
        'budget' => 30,
        // Each reader on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'open-badges-png' => 'tests/Fuzz/open-badges-png.php',
            'open-badges-svg' => 'tests/Fuzz/open-badges-svg.php',
            'open-badges-data-integrity' => 'tests/Fuzz/open-badges-data-integrity.php',
        ],
    ],
];

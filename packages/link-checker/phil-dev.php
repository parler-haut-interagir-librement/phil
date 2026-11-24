<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
use Phil\LinkChecker\Probe\HttpLinkProbe;

return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The srcset parser is asked for one address past the limit, so that a srcset longer than the links left
            // marks the content truncated: two past it mark it the same, as the loop stops at the limit either way.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*SrcsetParser::urls\\(\\$value, \\$this->maxLinks \\+ 1\\).*'],
            ],
            // The last catch keeps a request phil/safe-http refuses for another reason a link to review, should a
            // version of it add one: the client gives the probe no header and no body, so none of today's
            // InvalidRequest or RefusedRedirect can reach it.
            'CatchBlockRemoval' => [
                'ignore' => [HttpLinkProbe::class.'::probe'],
            ],
            // `U.u` is a numeric string: max() and the subtraction read it as the same float the cast gives.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\$now = \\(float\\) \\$this->clock->now\\(\\)->format\\(.U\\.u.\\);.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'deptrac' => [
        // The HTML5 parser of PHP's DOM extension, `Dom\HTMLDocument`, reads the links and the anchors as a browser
        // reads them; the default layers do not cover its namespace, which the library reaches through the Forbidden
        // layer, as phil/seo-check and phil/email-preview do.
        'ruleset' => [
            'PhilLinkChecker' => ['DefaultAllowed', 'PhilSafeHttp', 'PhilUrl', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The extraction of the links of a content, on HTML nobody wrote, from the seeds of tests/Fuzz/corpus/extract.
        'targets' => [
            'extract' => 'tests/Fuzz/extract.php',
        ],
    ],
];

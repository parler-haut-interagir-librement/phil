<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        'ruleset' => [
            // Uri\WhatWg\Url, of PHP's URI extension, reads the address; Deptrac counts it among the foreign classes.
            'PhilUrl' => ['DefaultAllowed', 'Forbidden', 'PhilEnums', 'PhilEnumsPhpstan'],
        ],
    ],
    'fuzz' => [
        // The readers of a Url, of the declared service hosts and of a ServiceUrl and its references, on addresses
        // nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'url-service-addresses' => 'tests/Fuzz/url-service-addresses.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The hosts are looked up with isset(): a value of false is as present as a value of true.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['\\$normalised\\[\\$this->normalise\\(\\$host\\)\\] = true;'],
            ],
            // The written host has no slash, and a special scheme reads `http:///host` as `http://host`: the host is the
            // same with or without the slash after it or before it.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['\\$ascii = WhatWgUrl::parse\\(.http://.\\.\\$written\\..\\/.\\)\\?->getAsciiHost\\(\\);'],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['\\$ascii = WhatWgUrl::parse\\(.http://.\\.\\$written\\..\\/.\\)\\?->getAsciiHost\\(\\);'],
            ],
            // An address read by AddressReader is http or https with a host after the slashes: its ASCII host is never null.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$host = \\(string\\) \\$parsed->getAsciiHost\\(\\);'],
            ],
            // The fast path of a plain ASCII host only skips idn_to_ascii(): the pattern of a host name checks the name
            // after it either way, so a name the fast path lets through or leaves to IDNA comes out the same; only the
            // rule of hyphens in third and fourth place, which the tests hold, tells the two apart.
            'MatchArmRemoval' => [
                'ignoreSourceCodeByRegex' => ['1 === preg_match\\(.\\/\\^\\[a-z0-9\\.-\\]\\+\\$\\/.*=> \\$name,'],
            ],
            'PregMatchRemoveCaret' => [
                'ignoreSourceCodeByRegex' => ['1 === preg_match\\(.\\/\\^\\[a-z0-9\\.-\\]\\+\\$\\/.*=> \\$name,'],
            ],
            'PregMatchRemoveDollar' => [
                'ignoreSourceCodeByRegex' => ['1 === preg_match\\(.\\/\\^\\[a-z0-9\\.-\\]\\+\\$\\/.*=> \\$name,'],
            ],
            // A Url refuses every whitespace in its text, so the path of a Vimeo address never ends with a new line: the
            // pattern of its path matches the same with or without the D modifier.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(KnownUrlType::Vimeo->accepts\\(\\$url\\) && 1 === preg_match\\(.*, \\$url->path, \\$match\\)\\) \\{',
                ],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/UrlType.php',
                'count' => 1,
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];

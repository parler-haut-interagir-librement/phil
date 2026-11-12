<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'deptrac' => [
        // The core of PHP outside the default: the HTML5 parser of the DOM extension reads the pages of other sites,
        // and the WHATWG parser of the URI extension resolves their addresses, which phil/url then checks.
        'ruleset' => [
            'PhilSocialCards' => ['DefaultAllowed', 'PhilFonts', 'PhilUrl', 'Forbidden'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The flood fill of a card that is black all over paints the same card from any of its pixels.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'imagefill\\(\\$image, 0, 0, .*',
                    // The fixture font, the only one the tests ship, starts every line at x 0 on the baseline: its
                    // box reads the same at the index of a y as at that of the x.
                    'return \\$box\\[2\\] - \\$box\\[0\\];',
                    // Only the first link is returned whatever the count asked: a second one found is dropped.
                    'return self::find\\(\\$text, 1\\)\\[0\\] \\?\\? null;',
                ],
            ],
            // GD clips what it draws to the card: a band from x -1 is the band from x 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['imagefilledrectangle\\(\\$image, 0, CardTemplate::HEIGHT - self::ACCENT_HEIGHT, .*'],
            ],
            // The fixture font starts every line at x 0: the left edge it adds or takes away is nothing.
            'Minus' => [
                'ignoreSourceCodeByRegex' => ['return \\$box\\[2\\] - \\$box\\[0\\];'],
            ],
            // The width of a line does not depend on the order of its characters in the measures of the tests, which
            // add up the characters without kerning: the ellipsis after the text or before it measures alike.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['while \\(\\[\\] !== \\$last && !\\$this->holds\\(implode.*'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of the tags of other sites' pages, on pages nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'social-cards-tags' => 'tests/Fuzz/social-cards-tags.php',
            // The finder of the links of the texts members write.
            'social-cards-links' => 'tests/Fuzz/social-cards-links.php',
        ],
    ],
    'docs' => ['public' => true],
];

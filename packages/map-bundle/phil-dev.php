<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A pixel size is at least one once rounded up from a positive scale, and a canvas is asked for with sizes of
            // at least one: the guards never change a value, and a canvas one pixel larger is never read past its content.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*max\\(1, \\(int\\) ceil\\(.*',
                    '.*imagecreatetruecolor\\(max\\(1, \\$width\\), max\\(1, \\$height\\)\\).*',
                    // The latitude of a position does not depend on its x, nor its longitude on its y.
                    '.*\\$plan->position\\(0, \\$plan->height\\)\\[0\\].*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*imagecreatetruecolor\\(max\\(1, \\$width\\), max\\(1, \\$height\\)\\).*',
                    '.*\\$plan->position\\(0, \\$plan->height\\)\\[0\\].*',
                    '.*imagefill\\(\\$canvas, 0, 0, \\$transparent\\).*',
                ],
            ],
            // The canvas is one transparent colour from end to end before anything is drawn, so a flood fill gives the same
            // image from any of its pixels; and libgd blends a pixel over a fully transparent one into the pixel itself,
            // with or without alpha blending.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*imagealphablending\\(\\$canvas, false\\).*'],
            ],
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*imagealphablending\\(\\$canvas, false\\).*'],
            ],
            // imagecolorallocatealpha() gives an integer on a true-colour image, false only past 256 colours of a palette;
            // the cast serves the type of the signature.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$transparent = \\(int\\) imagecolorallocatealpha.*'],
            ],
            // A string and a number are the same replacement to str_replace(), and a configured archive is a string by
            // its validation: the casts only serve the types of the signatures.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*str_replace\\(self::PLACEHOLDERS.*',
                    '.*setArguments\\(\\[\\(string\\) \\$pmtiles\\[.url.\\].*',
                ],
            ],
            // Without exactly one end, the period is refused before; with both or none, "or" and "and" agree.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['.*\\$start instanceof DateTimeImmutable && \\$end instanceof DateTimeImmutable && \\$end <= \\$start.*'],
            ],
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*\\$states = !\\$start instanceof DateTimeImmutable \\|\\| .*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['chromedriver', 'Imagick', 'libvips']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];

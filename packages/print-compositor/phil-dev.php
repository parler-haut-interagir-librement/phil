<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The worker-mode page runs inside FrankenPHP, which gives it `frankenphp_handle_request()`, unknown to PHPStan.
        'excludePaths' => ['tests/Worker/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Item.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // GD clips a rectangle to its image and orders its corners: the canvas fill covers every pixel from -1 or to
            // one past the edge as well. The scratch canvas of `TextFitter::ink()` only has to hold the line: a margin
            // a pixel or an em wider or narrower still holds it, and its vertical bounds, read from another corner of
            // FreeType's box, move it inside that margin. The ink is measured on the pixels either way.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'imagefilledrectangle\\(\\$image, 0, 0, \\$width - 1, \\$height - 1, \\$colour\\);',
                    '\\[\\$top, \\$bottom\\] = \\[\\$bounds\\[7\\], \\$bounds\\[3\\]\\];',
                    '\\$margin = 2 \\+ \\(int\\) ceil\\(\\$gdSize\\);',
                    '\\$width = \\$right - \\$left \\+ 2 \\* \\$margin;',
                    '\\$height = \\$bottom - \\$top \\+ 2 \\* \\$margin;',
                ],
            ],
            // See `DecrementInteger`.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'imagefilledrectangle\\(\\$image, 0, 0, \\$width - 1, \\$height - 1, \\$colour\\);',
                    '\\[\\$top, \\$bottom\\] = \\[\\$bounds\\[7\\], \\$bounds\\[3\\]\\];',
                    '\\$margin = 2 \\+ \\(int\\) ceil\\(\\$gdSize\\);',
                    '\\$width = \\$right - \\$left \\+ 2 \\* \\$margin;',
                    '\\$height = \\$bottom - \\$top \\+ 2 \\* \\$margin;',
                ],
            ],
            // See `DecrementInteger`.
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    'imagefilledrectangle\\(\\$image, 0, 0, \\$width - 1, \\$height - 1, \\$colour\\);',
                    '\\$width = \\$right - \\$left \\+ 2 \\* \\$margin;',
                    '\\$originX = \\$margin - \\$left;',
                ],
            ],
            // See `DecrementInteger`: the margin of the scratch canvas.
            'RoundingFamily' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$margin = 2 \\+ \\(int\\) ceil\\(\\$gdSize\\);',
                ],
            ],
            // Every environment the tests run in has GD with FreeType, PNG and WebP: removing the check only changes
            // the message a GD build without them gives, which no test can build.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'Gd::require\\(.*',
                ],
            ],
            // FreeType's bounding box only spares measuring the ink of sizes far too large: a size the gate lets
            // through is measured on its pixels, and found too large there.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$estimate->width > \\$box->width \\* self::ESTIMATE_MARGIN.*',
                ],
            ],
            // See `GreaterThan`.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$estimate->width > \\$box->width \\* self::ESTIMATE_MARGIN.*',
                ],
            ],
            // On the grid of half points, the last step `<=` adds is the minimum, which is tried next anyway: twice,
            // with the same result.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$step = 0, \\$steps = \\(\\$max - \\$min\\) / self::STEP; \\$step < \\$steps; \\+\\+\\$step\\) \\{',
                ],
            ],
        ],
    ],
    // The page `composer bench:worker phil/print-compositor` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 200, 'warmup' => 50],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];

<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['configurator', 'CSSOM', 'embeddables', 'Flysystem', 'HMAC', 'importmap', 'IPTC', 'libvips']],
    'fuzz' => [
        // The purifier of SVG images, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'svg-purifier' => 'tests/Fuzz/svg-purifier.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // `VipsImageProcessor::find()` reads the process's `PATH` when none is given: the test puts its stand-in
            // directory there for one call and gives `PATH` back in a `finally`.
            [
                'identifier' => 'disallowed.function',
                'message' => '#^Calling putenv\\(\\) is forbidden#',
                'path' => 'tests/Unit/Infrastructure/VipsImageProcessorTest.php',
                'count' => 2,
            ],
            // à corriger : API interne de EasyCorp à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/Infrastructure/EasyAdminTest.php',
                'count' => 2,
            ],
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'path' => 'src/Domain/Exception/MediaInUse.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Infrastructure/Validator/MediaFile.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/ImageProcessor.php',
                    'src/Application/MediaReferences.php',
                    'src/Application/MediaStorage.php',
                    'src/Application/Transform/Transformer.php',
                    'src/Domain/Repository/MediaRepository.php',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // How a medium was made is declared with the digital source types of phil/creation-disclosure, an optional
        // library (suggested) that the domain model reads when it is installed.
        'ruleset' => [
            'PhilMediaBundleDomain' => ['DefaultAllowed', 'PhilCreationDisclosure'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // `GdImageProcessor`: GD never decodes an image narrower or lower than one pixel, so `max(0, …)` reads the
            // same size as `max(1, …)`, which only gives the geometry its positive type; and GD clips a rectangle to the
            // canvas, so filling from -1 or up to the width or height itself paints the same pixels. Filling one pixel
            // short stays mutated.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(width|height) = max\\(1, images[xy]\\(\\$source\\)\\);',
                    'imagefilledrectangle\\(\\$target, 0, 0, \\$step->canvasWidth - 1, \\$step->canvasHeight - 1, .*',
                    // `VipsImageProcessor`: the random part only makes the working directory's name unguessable; seven
                    // or nine bytes do it as well as eight, and the tests never read the name.
                    '\\$directory = sys_get_temp_dir\\(\\)\\.\'/phil-media-vips-\'\\.sodium_bin2hex\\(random_bytes\\(8\\)\\);',
                ],
            ],
            // See `DecrementInteger`: the working directory of `VipsImageProcessor`.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$directory = sys_get_temp_dir\\(\\)\\.\'/phil-media-vips-\'\\.sodium_bin2hex\\(random_bytes\\(8\\)\\);',
                ],
            ],
            // `VipsImageProcessor`: Symfony Process writes each argument of the command as a string, an integer as its
            // digits; the casts only give `run()` its list of strings.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$file = \\$this->run\\(\\$directory, \'(extract_area|embed)\', .*',
                ],
            ],
            // `MediaLibrary::placeholder()`: the `data:image/webp;base64,` prefix holds 23 characters and base64 a
            // multiple of 4, so a placeholder is 4095 or 4099 characters long, never the 4096 of the limit itself.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\\\strlen\\(\\$placeholder\\) > self::PLACEHOLDER_MAX\\) \\{',
                ],
            ],
            // `SvgMinifier::minify()`: saving an element of a document just parsed does not fail, so the `false` that
            // would throw is never returned; the throw keeps the return type a string.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    'return false === \\$minified \\? throw InvalidMedia::because\\(\'The SVG image cannot be written\\.\'\\) : \\$minified;',
                ],
            ],
            // See `DecrementInteger`: `+ 1` fills past the canvas, which GD clips.
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    'imagefilledrectangle\\(\\$target, 0, 0, \\$step->canvasWidth - 1, \\$step->canvasHeight - 1, .*',
                ],
            ],
            // The `COUNT` of `DoctrineMediaReferences`: PDO already returns it as an integer on SQLite, where the tests
            // run; the cast protects the drivers that would return a numeric string.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$count = \\(int\\) \\$this->entityManager->createQuery\\(.*',
                ],
            ],
        ],
    ],
];

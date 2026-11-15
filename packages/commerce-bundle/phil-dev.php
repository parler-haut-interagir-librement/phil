<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        // The amount a buyer chose on a line, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'chosen-amount' => 'tests/Fuzz/chosen-amount.php',
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
                'paths' => [
                    'src/Application/Port/DocumentNumbering.php',
                    'src/Application/Port/OptionDrawer.php',
                    'src/Application/Port/OrderProcessor.php',
                    'src/Application/Port/PaymentAttemptStateStore.php',
                    'src/Application/Port/PaymentGateway.php',
                    'src/Application/Port/SellableAvailability.php',
                    'src/Application/Port/SellableOptions.php',
                    'src/Application/Port/SellableResolver.php',
                    'src/Application/Port/TaxRateResolver.php',
                    'src/Application/Port/UnitPriceResolver.php',
                    'src/Domain/Repository/ChannelRepository.php',
                    'src/Domain/Repository/OrderRepository.php',
                    'src/Domain/Repository/PaymentRepository.php',
                    'src/Domain/Repository/QuoteRepository.php',
                    'src/Domain/Repository/ReceiptRepository.php',
                ],
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // A tag placed after the service of a package the bundle does not depend on names it by a string: `::class`
            // would make the bundle depend on that package in the eyes of Deptrac and of the dependency analysis, while
            // the constraint is ignored when the package is not installed.
            StringClassNameToClassConstantRector::class => [
                'src/PhilCommerceBundle.php',
            ],
        ],
    ],
    'docs' => [
        'public' => true,
        // A sale paid before its goods are available, or open before the others; what modules sell; the names of the
        // Unicode data, of regular expressions, of a database's nested transaction, and of projects the modules echo;
        // the French labels a receipt is given as an example.
        'words' => ['preorder', 'preorders', 'presale', 'sellables', 'savepoint', 'CLDR', 'PCRE', 'Sylius', 'Payum', 'incluse', 'incluses'],
    ],
    'infection' => [
        'mutators' => [
            // Limits declared as constants: mutating their declaration produces a mutant no reasonable test kills; the
            // comparisons that use them stay mutated.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                ],
            ],
            // See `IncrementInteger`.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                ],
            ],
            // Two values that are never null where they are cast: a value still to draw was described by its option, which
            // names its drawer (`OptionValue::described()` requires it, and the stored form is read back through it), and a
            // value checked as chosen once the values to draw are handled holds its text or its choice.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$name = \\(string\\) \\$line->configuration->value\\(\\$option\\)\\?->drawer;',
                    '\\$chosen = \\(string\\) \\$value->value;',
                ],
            ],
            // A deposit holds a share or a fixed amount, never both nor neither: past the fixed amount, its share is an
            // integer, and the cast only tells PHPStan so. A manual discount is the same: past its amount, it holds its
            // share.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$share = \\(int\\) \\$this->basisPoints;',
                    '\\$share = intdiv\\(\\$base->minorUnits \\* \\(int\\) \\$this->basisPoints, self::WHOLE\\);',
                ],
            ],
            // The codes `LineConfiguration::pending()` gives are those of its values: `value()` finds each of them.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$name = \\(string\\) \\$line->configuration->value\\(\\$option\\)\\?->drawer;',
                ],
            ],
            // Unpacked as arguments, integer keys are positional whatever their values: the supplements reach
            // `Money::sum()` in the same order with or without `array_values()`.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\[\\] === \\$supplements \\? null : Money::sum\\(\\.\\.\\.array_values\\(\\$supplements\\)\\);',
                ],
            ],
        ],
    ],
];

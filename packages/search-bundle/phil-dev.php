<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/DbalSearchIndexTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'paths' => [
                    'tests/Benchmark/IndexOnFlushBench.php',
                    'tests/Unit/Application/SearchTest.php',
                    'tests/Unit/Infrastructure/DbalSearchIndexTest.php',
                ],
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Infrastructure/Meilisearch/SearchFailed.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/DocumentProvider.php',
                    'src/Application/SearchIndex.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The first length tried is the most words a phrase has, or the words left when they are fewer: more, and the
            // slice is the words left all the same, which the map knows or not as it does at that length.
            'Minus' => [
                'ignoreSourceCodeByRegex' => ['for \\(\\$length = min\\(\\$this->longest\\[\\$language\\], \\$count - \\$start\\); .*'],
            ],
            // A length of 0 is the empty phrase, which no rule has.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['for \\(\\$length = min\\(\\$this->longest\\[\\$language\\], \\$count - \\$start\\); .*'],
            ],
            // Without a rule the longest phrase is 0 or -1 words: the loop over the lengths does not run for either.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$longest = 0;',
                    // The key `array_fill()` starts from is lost in `implode()`.
                    '.*array_fill\\(0, \\\\count\\(\\$alternatives\\).*',
                    // A score is only compared with the others: one more or less for every document changes no order.
                    '\\$score = 0;',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*array_fill\\(0, \\\\count\\(\\$alternatives\\).*'],
            ],
        ],
    ],
    'deptrac' => [
        // The bundle registers the Loupe index when the optional adapter, phil/search-loupe, is installed, and the file
        // store of synonyms when phil/search-synonyms is.
        'ruleset' => [
            'PhilSearchBundle' => ['PhilSearchBundleDomain', 'PhilSearchBundleApplication', 'PhilSearchBundleInfrastructure', 'DefaultAllowed', 'Forbidden', 'PhilSearchLoupe', 'PhilSearchSynonyms'],
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // The search engine, the primary part of a language tag, and the driver Panther runs Chrome with.
        'words' => ['Meilisearch', "Meilisearch's", 'subtag', 'chromedriver'],
    ],
];

<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
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
];

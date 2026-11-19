<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        'ignoreErrors' => [
            // The repositories stay in Domain\Repository, as Arkitect requires of the ports of a domain's models.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => ['src/Domain/Repository/ConversationRepository.php', 'src/Domain/Repository/ReceptionRepository.php'],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A column of a primary key is NOT NULL whatever its declaration says: the schema makes it so.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::column\\(.(participant|blocked)., Types::STRING, true, ParticipantId::MAX_LENGTH\\).*',
                ],
            ],
            // The pages need Twig, the router and the CSRF manager, which every installation the tests can build has
            // together: no test makes one of them absent while the others stay.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(!class_exists\\(Environment::class\\) \\|\\| !interface_exists\\(UrlGeneratorInterface::class\\) \\|\\| !interface_exists\\(CsrfTokenManagerInterface::class\\)\\) \\{.*',
                ],
            ],
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    // The conversion only narrows what a driver returns to the string the type says: the columns read are
                    // text columns, which every driver of the bundle returns as strings already.
                    '.*array_map\\(self::text\\(\\.\\.\\.\\), .*fetchFirstColumn.*',
                    // The names of the registered bundles are strings, except those a PHP array turns into integers, which
                    // `in_array()` of a name never matches.
                    '.*array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$bundles\\)\\).*',
                ],
            ],
            // The same narrowing of a value a text column holds.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*is_scalar\\(\\$value\\) \\? \\(string\\) \\$value.*',
                ],
            ],
            // The count of a delete is an integer on every driver of the bundle: the cast only serves the type.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \\(int\\) \\$settings \\+ \\(int\\) \\$blocks;.*',
                ],
            ],
        ],
    ],
];

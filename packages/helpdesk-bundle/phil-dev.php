<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The name of the package.
    'docs' => ['public' => true, 'words' => ['helpdesk']],
    'phpstan' => [
        'ignoreErrors' => [
            // The repository stays in Domain\Repository, as Arkitect requires of the ports of a domain's models.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => ['src/Domain/Repository/TicketRepository.php'],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A column of a primary key is NOT NULL whatever its declaration says: the schema makes it so.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::column\\(.conversation_id., Types::STRING, true, 36\\).*',
                ],
            ],
            // The pages need Twig, the router and the CSRF manager, which every installation the tests can build has
            // together: no test makes one of them absent while the others stay.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(!class_exists\\(Environment::class\\) \\|\\| !interface_exists\\(UrlGeneratorInterface::class\\) \\|\\| !interface_exists\\(CsrfTokenManagerInterface::class\\)\\) \\{.*',
                ],
            ],
            // `offset()` brings any page before the first back to the first: a default of 0 or of 1 reads the same page.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*public function (requests|desk)\\(.*int \\$page = 1\\): array.*',
                ],
            ],
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    // A queue name is stringable: the driver binds it as the string the map gives.
                    '.*\\$parameters = \\[array_map\\(static fn \\(QueueName \\$queue\\): string => \\$queue->value, \\$queues\\)\\];.*',
                    // The names of the registered bundles are strings, except those a PHP array turns into integers, which
                    // `in_array()` of a name never matches.
                    '.*array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$bundles\\)\\).*',
                ],
            ],
            // A string is how a driver binds a parameter without a type: naming the type of the requester only says so.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*WHERE requester = \\?., \\[\\$requester->value\\], \\[ParameterType::STRING\\], \\$limit, \\$offset\\).*',
                ],
            ],
            // The count of an update is an integer on every driver of the bundle, and a text column is a string: the
            // casts only serve the types.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \\(int\\) \\$requested \\+ \\(int\\) \\$assigned;.*',
                ],
            ],
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*is_scalar\\(\\$value\\) \\? \\(string\\) \\$value.*',
                ],
            ],
        ],
    ],
];

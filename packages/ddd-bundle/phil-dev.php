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
                'path' => 'tests/Integration/Infrastructure/Doctrine/IdentifierTypeRoundTripTestCase.php',
                'count' => 1,
            ],
            // à corriger : new static() dans une classe abstraite, constructeur à rendre final ou fabrique à revoir.
            [
                'identifier' => 'new.staticInAbstractClassStaticMethod',
                'path' => 'src/Domain/Identifier/Identifier.php',
                'count' => 2,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'paths' => [
                    'tests/Functional/App/Membership/EmailAlreadyRegistered.php',
                    'tests/Functional/App/Membership/UnknownMember.php',
                    'tests/Unit/Infrastructure/Messenger/Fixture/ExampleDomainError.php',
                ],
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Bus/Command.php',
                    'src/Application/Bus/CommandBus.php',
                    'src/Application/Bus/CommandHandler.php',
                    'src/Application/Bus/Query.php',
                    'src/Application/Bus/QueryBus.php',
                    'src/Application/Bus/QueryHandler.php',
                    'src/Domain/Event/DomainEvent.php',
                ],
            ],
        ],
    ],
];

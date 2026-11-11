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
            // à corriger : complexité cognitive au-dessus de 25, à découper : KnownMessenger::fromLink() (27).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'src/KnownMessenger.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/MessengerType.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The arm of the six messengers that read no link, in `KnownMessenger::fromLink()`: the method is only
            // called for the eight others, and the arm only exists so that PHPStan sees an exhaustive `match`.
            'MatchArmRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'self::Viber, self::Xmpp, self::Discord, self::WeChat, self::IMessage, self::Session => null,',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];

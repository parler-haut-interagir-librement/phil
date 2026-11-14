<?php

declare(strict_types=1);

use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'path' => 'src/Domain/Exception/InvalidEntry.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Application/NotificationFailed.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/CurrentSubject.php',
                    'src/Application/EntryNotifier.php',
                    'src/Domain/Repository/FormRepository.php',
                    'src/Domain/Subject/EmailDigester.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The places are given back only once taken, and none is taken without the places: with none, the list is empty.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->places\\?->release\\(.*',
                    // The names of the errors come from the form's own fields, so the field is always there to be read.
                    '\\$message = \'disqualified\' === \\$error.*',
                    '\\$message = \\$this->translator\\?->trans\\(.*',
                ],
            ],
            // The only string value a field disqualifies is a choice of its own, which no other problem comes before.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['\\$message = \'disqualified\' === \\$error.*'],
            ],
            // SQLite gives the count as the integer it is; MySQL and PostgreSQL give a numeric string, which the cast turns in one.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return is_numeric\\(\\$taken\\) \\? \\(int\\) \\$taken : 0;',
                    '\\$years = \\(int\\) substr\\(\\$on, 0, 4\\).*',
                ],
            ],
            // The dates are validated as YYYY-MM-DD before the age is taken: PHP's integer cast stops at the first non-digit
            // and arithmetic on numeric strings gives the same integer, so a longer slice, an unsliced date or a missing
            // cast all read the same year.
            'UnwrapSubstr' => ['ignoreSourceCodeByRegex' => ['\\$years = \\(int\\) substr\\(\\$on, 0, 4\\).*']],
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['\\$years = \\(int\\) substr\\(\\$on, 0, 4\\).*']],
        ],
    ],
    // The entry point of the browser suite's application.
    'js' => ['paths' => ['tests/Browser/App/assets']],
    'docs' => [
        'public' => true,
        // The keyed digests of the addresses, the domain's mail policy, the library the sealed values rest on, and the
        // driver of the browser suite.
        'words' => ['HMAC', 'DMARC', 'libsodium', 'chromedriver'],
    ],
    'rector' => [
        'skip' => [
            // The test kernel registers the mail recorder with the kernel.event_subscriber tag and no autoconfiguration:
            // as a listener by attribute, it would no longer be registered at all.
            EventSubscriberToAttributeRector::class => ['tests/Functional/App/SentMessages.php'],
        ],
    ],
];

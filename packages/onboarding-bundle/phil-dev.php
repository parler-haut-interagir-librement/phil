<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // Question::pick() returns the choices in the order of the question, as a new list: the keys of the posted
            // identifiers never go further, and array_values() only gives the list<string> of the signature.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['\\$picked = \\\\is_array\\(\\$choices\\) \\? array_values\\(.*'],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // The repository stays in Domain\Repository, as Arkitect requires of the ports of a domain's models.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => ['src/Domain/Repository/ProgressRepository.php'],
            ],
        ],
    ],
];

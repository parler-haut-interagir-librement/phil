<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The padding of RSA key transport and the provisioning protocol the bundle leaves to others.
        'words' => ['OAEP', 'SCIM'],
    ],
    'infection' => [
        'mutators' => [
            // The encryption pairs are kept under any key but the one of the signing pair, which is 0: the number only
            // tells the two apart.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$this->keys\\[1\\] \\?\\?= array_map\\(.*'],
            ],
            // `isset()` is true of a key whose value is false: the services already followed are told by their key alone.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$id\\] = true;'],
            ],
        ],
    ],
];

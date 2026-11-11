<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // phil/svg-blocks (with phil/ujo) and phil/paged-media are optional integrations, suggested rather than
        // required: the infrastructure that wires and implements them sees their layers, as it would see them from
        // require-dev, and the rest of the bundle still does not.
        'ruleset' => [
            'PhilQrCodeBundleInfrastructure' => [
                'PhilQrCodeBundleDomain',
                'PhilQrCodeBundleApplication',
                'DefaultAllowed',
                'Forbidden',
                'PhilQrCode',
                'PhilPagedMedia',
                'PhilSvgBlocks',
                'PhilUjo',
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];

<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'deptrac' => [
        // The checker reads HTML through the DOM extension of PHP, and the previews through the application layer of
        // phil/email-templates-bundle — its rendered email, its preview contract and the value objects they carry —,
        // never through its infrastructure.
        'ruleset' => [
            'PhilEmailPreview' => ['DefaultAllowed', 'Forbidden', 'PhilEmailTemplatesBundleDomain', 'PhilEmailTemplatesBundleApplication'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The headers of Symfony Mime are all HeaderInterface: the test only tells PHPStan so.
            'InstanceOf_' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\$header instanceof HeaderInterface\\) \\{.*'],
            ],
            // `isset()` is true of a key whose value is false: the links already seen are told by their key alone.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$href\\] = true;'],
            ],
        ],
    ],
];

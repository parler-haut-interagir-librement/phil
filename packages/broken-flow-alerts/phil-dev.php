<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP; the Notifier sender is an adapter, which alone sees Symfony Notifier.
        'layers' => [
            'PhilBrokenFlowAlerts' => '^Phil\\\\BrokenFlowAlerts\\\\(?!Notifier\\\\).*',
            'PhilBrokenFlowAlertsAdapter' => '^Phil\\\\BrokenFlowAlerts\\\\Notifier\\\\.*',
        ],
        'ruleset' => [
            'PhilBrokenFlowAlerts' => ['DefaultAllowed'],
            'PhilBrokenFlowAlertsAdapter' => ['PhilBrokenFlowAlerts', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];

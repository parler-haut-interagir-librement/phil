<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. One exception: the optional
 * integration — `symfony/notifier` for `NotifierAlertSender` —, required in development only and suggested: an
 * application that sends its alerts that way installs it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/notifier'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);

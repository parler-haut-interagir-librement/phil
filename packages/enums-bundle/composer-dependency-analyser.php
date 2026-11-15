<?php

declare(strict_types=1);

use Phil\EnumsBundle\Tests\Unit\Pair\Left;
use Phil\EnumsBundle\Tests\Unit\Pair\Right;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Four exceptions:
 *
 * - the `service()` function of the container configurator, which the analyser does not see as declared by
 *   `symfony/dependency-injection` although that package defines it;
 * - the optional integrations — `doctrine/dbal`, `doctrine/orm`, `doctrine/persistence`, `symfony/console`,
 *   `symfony/expression-language`, `symfony/http-foundation`, `symfony/http-kernel`, `symfony/serializer`,
 *   `symfony/translation`, `symfony/workflow`, API Platform, NelmioApiDoc and its `zircote/swagger-php`,
 *   `symfony/type-info`, MakerBundle, and `ext-intl` for the ICU catalogues of the constraint messages —,
 *   which the production code uses but only requires in development and suggests: the bundle registers each one
 *   only when its package is installed;
 * - the two enums of `tests/Unit/Pair/pair.php`, which no autoloader maps on purpose: the test loads the file
 *   itself, to see the finder read two enums from one file;
 * - `hidden_classes()` of `tests/Unit/Fixture/class_exists.php`, which the test loads itself, to hide classes from the
 *   bundle's `class_exists()` one at a time.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'doctrine/persistence', 'symfony/console', 'symfony/expression-language', 'symfony/http-foundation', 'symfony/http-kernel', 'symfony/serializer', 'symfony/translation', 'symfony/workflow', 'api-platform/json-schema', 'api-platform/metadata', 'nelmio/api-doc-bundle', 'zircote/swagger-php', 'symfony/type-info', 'symfony/maker-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClasses([
        Left::class,
        Right::class,
    ])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Phil\EnumsBundle\hidden_classes',
    ]);

<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Rector\Configuration\RectorConfigBuilder;

/**
 * The shared Rector configuration of phil/dev-tools. The paths and skips come from phil-dev.php.
 *
 * No member of this monorepo is installed in vendor/: their classes load through the member autoloader, which
 * `autoload-members.php` registers for the root that Composer names. Inside Rector, that root is Rector's own, in
 * vendor-bin/, and a trait of another package stays unknown when Rector loads a class that uses it. The namespaces of
 * every member are therefore added here to the project's own Composer loader, from each member's composer.json.
 *
 * @var Closure(string): RectorConfigBuilder $shared
 */
$shared = require __DIR__.'/vendor/phil/dev-tools/config/rector.php';

/** @var ClassLoader $loader */
$loader = require __DIR__.'/vendor/autoload.php';

foreach (glob(__DIR__.'/packages/*/composer.json') ?: [] as $file) {
    $composer = json_decode((string) file_get_contents($file), true);

    foreach (['autoload', 'autoload-dev'] as $section) {
        foreach ($composer[$section]['psr-4'] ?? [] as $namespace => $directories) {
            foreach ((array) $directories as $directory) {
                $loader->addPsr4($namespace, dirname($file).'/'.$directory);
            }
        }
    }
}

return $shared(__DIR__);

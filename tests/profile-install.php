<?php

declare(strict_types=1);

/*
 * Installs each profile of the monorepo — a package of type metapackage — on its own in a new project, its members
 * from Packagist, then boots a Symfony kernel with the bundles its members bring.
 *
 * `composer packages` installs a package with its siblings from the monorepo's path repositories; a profile only
 * keeps its promise if its members, at the constraints it gives, resolve from Packagist as published. The project gets
 * the profile from a path repository and everything else from Packagist, with the stability a PHIL alpha needs
 * (minimum-stability alpha, prefer-stable). The kernel is the one of the profile's tests
 * (tests/Functional/App/TestKernel.php and its config/bundles.php), copied into the project; the packages an
 * application brings beside the profile for its members to boot, when there are any, are in the JSON object of
 * tests/Functional/App/application-requires.json, by name and constraint.
 *
 *     composer profiles                        every profile, on the PHP running Composer
 *     composer profiles -- profile-editorial   one profile, by directory or Composer name
 *     composer profiles:next                   every profile on the next PHP of the matrix, in its Docker image
 *
 * It needs the network. The projects stay under var/profile-install/<PHP version>/ until the next run.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * A JSON file read as an array, or an empty array when it is missing or holds something else.
 *
 * @return array<mixed>
 */
$readJson = static function (string $file): array {
    if (!is_file($file)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);

    return is_array($data) ? $data : [];
};

/**
 * The string values of an array, by their string keys.
 *
 * @return array<string, string>
 */
$strings = static function (mixed $values): array {
    $strings = [];

    foreach (is_array($values) ? $values : [] as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $strings[$key] = $value;
        }
    }

    return $strings;
};

$root = dirname(__DIR__);
$version = \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION;
$base = $root.'/var/profile-install/'.$version;
$arguments = $_SERVER['argv'] ?? [];
$wanted = array_values(array_filter(array_slice(is_array($arguments) ? $arguments : [], 1), is_string(...)));
$filesystem = new Filesystem();
$failures = [];
$manifests = glob($root.'/packages/*/composer.json');

foreach (false === $manifests ? [] : $manifests as $manifest) {
    $directory = dirname($manifest);
    $composer = $readJson($manifest);
    $name = $composer['name'] ?? null;

    if ('metapackage' !== ($composer['type'] ?? null) || !is_string($name)) {
        continue;
    }

    if ([] !== $wanted && !in_array(basename($directory), $wanted, true) && !in_array($name, $wanted, true)) {
        continue;
    }

    $autoloadDev = $composer['autoload-dev'] ?? null;
    $namespace = array_key_first($strings(is_array($autoloadDev) ? $autoloadDev['psr-4'] ?? null : null));
    $app = $directory.'/tests/Functional/App';

    echo "== {$name} on PHP {$version}\n";

    if (null === $namespace || !is_file($app.'/TestKernel.php') || !is_file($app.'/config/bundles.php')) {
        $failures[] = "{$name}: no tests/Functional/App/TestKernel.php with its config/bundles.php";

        continue;
    }

    $kernel = $namespace.'Functional\App\TestKernel';
    $work = $base.'/'.basename($directory);
    $filesystem->remove($work);
    $filesystem->mkdir($work.'/var/tmp');
    $filesystem->mirror($app, $work.'/app');

    $project = [
        'name' => 'phil/profile-install-check',
        'description' => 'A new project that installs '.$name.' alone.',
        'type' => 'project',
        'require' => [
            'php' => '>=8.5',
            $name => 'dev-main',
            'symfony/framework-bundle' => '^8.2',
            'symfony/twig-bundle' => '^8.2',
            ...$strings($readJson($app.'/application-requires.json')),
        ],
        'repositories' => [
            ['type' => 'path', 'url' => $directory, 'options' => ['symlink' => false, 'versions' => [$name => 'dev-main']]],
        ],
        'minimum-stability' => 'alpha',
        'prefer-stable' => true,
        'config' => ['allow-plugins' => false, 'sort-packages' => true],
    ];
    $filesystem->dumpFile($work.'/composer.json', json_encode($project, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
    $filesystem->dumpFile($work.'/boot.php', <<<PHP
        <?php

        declare(strict_types=1);

        require __DIR__.'/vendor/autoload.php';
        require __DIR__.'/app/TestKernel.php';

        \$kernel = new {$kernel}('test', true);
        \$kernel->boot();
        \$missing = [];

        foreach (array_keys(require __DIR__.'/app/config/bundles.php') as \$class) {
            \$short = substr(\$class, (int) strrpos(\$class, '\\\\') + 1);

            if (!array_key_exists(\$short, \$kernel->getBundles())) {
                \$missing[] = \$class;
            }
        }

        if ([] !== \$missing) {
            fwrite(STDERR, 'Not registered: '.implode(', ', \$missing)."\\n");

            exit(1);
        }

        echo 'The kernel booted with '.count(\$kernel->getBundles())." bundles.\\n";

        PHP);

    $steps = [
        'composer update' => ['composer', 'update', '--no-interaction', '--no-progress', '--prefer-dist'],
        'composer show' => ['composer', 'show', '--no-dev', 'phil/*'],
        'kernel boot' => [\PHP_BINARY, 'boot.php'],
    ];

    foreach ($steps as $label => $step) {
        $process = new Process($step, $work, ['TMPDIR' => $work.'/var/tmp'], timeout: 900);
        $process->run(static function (string $type, string $output): void {
            fwrite(Process::ERR === $type ? \STDERR : \STDOUT, $output);
        });

        if (!$process->isSuccessful()) {
            $failures[] = "{$name}: {$label} failed on PHP {$version}";

            break;
        }
    }
}

if ([] !== $failures) {
    fwrite(\STDERR, implode("\n", $failures)."\n");

    exit(1);
}

echo "Every profile installs alone from Packagist and boots on PHP {$version}.\n";

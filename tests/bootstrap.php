<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;

/*
 * The test kernels of the packages keep their container caches under sys_get_temp_dir(), in directories named after
 * the package only. Another checkout of the same packages would then share them, and a test would load a container
 * compiled from the other checkout's files. The temporary directory of a run is therefore this checkout's own, named
 * after its path, and short, since some tests print a temporary path in a console block of fixed width. PHP reads
 * TMPDIR once, at the first call of sys_get_temp_dir(), so it is set before anything else runs, and the processes a test
 * starts inherit it.
 *
 * ParaTest runs the suites in several processes at once and gives each its own TEST_TOKEN: each process then has a
 * directory of its own, so that two processes never share a container cache, an SQLite file or the temporary files a
 * test counts.
 */
$root = dirname(__DIR__);
$token = getenv('TEST_TOKEN');
$temporary = '/tmp/'.basename($root).'-'.hash('crc32b', $root).(is_string($token) && '' !== $token ? '-'.$token : '');

if (!is_dir($temporary) && !mkdir($temporary, recursive: true) && !is_dir($temporary)) {
    throw new RuntimeException(sprintf('The temporary directory "%s" cannot be created.', $temporary));
}

putenv('TMPDIR='.$temporary); // @phpstan-ignore disallowed.function (overwriting TMPDIR is the purpose of this file)
$_ENV['TMPDIR'] = $_SERVER['TMPDIR'] = $temporary;

require $root.'/vendor/autoload.php';

/*
 * The integration tests of every package share the database of each server, phil_test, and many create, fill and drop
 * tables of the same names. Two ParaTest processes, or two checkouts running at once, would then drop each other's
 * tables in the middle of a test. Each process of each checkout therefore gets a database of its own on the servers
 * whose account may create databases: phil_test_, the hash of the checkout's path, then the TEST_TOKEN of ParaTest.
 * The database is created empty when it is missing; a server whose account may not create one keeps its URL unchanged.
 * A process started by another that loaded this file, a ParaTest worker or a PHP process a test starts, inherits the URL
 * already given: the suffix of this checkout is then replaced, never added a second time, and a process with the same
 * TEST_TOKEN works in the same database.
 */
$checkout = hash('crc32b', $root);

foreach (['PHIL_TEST_MYSQL_URL', 'PHIL_TEST_POSTGRESQL_URL'] as $variable) {
    $url = getenv($variable);

    if (!is_string($url) || 1 !== preg_match('~^([a-z0-9+]+://[^/?#]+/)([A-Za-z0-9_]+)(.*)$~D', $url, $parts)) {
        continue;
    }

    $database = preg_replace('~_'.$checkout.'(?:_\d+)?$~D', '', $parts[2]).'_'.$checkout.(is_string($token) && 1 === preg_match('~^\d+$~D', $token) ? '_'.$token : '');

    try {
        $server = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));

        if ($server->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (false === $server->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$database])) {
                $server->executeStatement('CREATE DATABASE '.$database);
            }
        } else {
            $server->executeStatement('CREATE DATABASE IF NOT EXISTS '.$database);
        }

        $server->close();
    } catch (Exception) {
        continue;
    }

    $url = $parts[1].$database.$parts[3];
    putenv($variable.'='.$url); // @phpstan-ignore disallowed.function (the tests read the URL from the environment)
    $_ENV[$variable] = $_SERVER[$variable] = $url;
}

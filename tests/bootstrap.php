<?php

declare(strict_types=1);

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

<?php

declare(strict_types=1);

/*
 * The media archive's verification on tar files nobody wrote: whatever a file holds, `verify()` counts its files or
 * refuses it with `BackupFailed`, and creates nothing — not even its media directory, which is never there.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Media\MediaArchive;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$directory = sys_get_temp_dir().'/phil-backup-fuzz-'.getmypid();
$file = $directory.'.tar';
$archive = new MediaArchive(['media' => $directory.'/media', 'other' => $directory.'/other']);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($archive, $directory, $file): void {
    file_put_contents($file, $input);

    try {
        $archive->verify($file);
    } catch (BackupFailed) {
        // The refusal the archive promises for a file it cannot read.
    }

    if (file_exists($directory)) {
        throw new LogicException('Verifying an archive created a directory.');
    }
});
$config->setMaxLen(8192);

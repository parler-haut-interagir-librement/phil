<?php

declare(strict_types=1);

/*
 * The reader of the quarantine's records and of the identifiers an operator's requests carry, on text nobody wrote:
 * a record of any contents still reads as a file of the quarantine, its name a string and its size never negative;
 * an identifier that is not one the quarantine gave names nothing, and deleting it removes nothing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

// One directory of the temporary directory, the same from one run to the next: the fuzzer stops without cleaning up.
$root = sys_get_temp_dir().'/phil-upload-scan-fuzz';
$directory = $root.'/quarantine';
$id = str_repeat('ab', 16);

if (!is_dir($directory)) {
    mkdir($directory, 0o700, true);
}

// A file beside the quarantine, which no identifier may reach.
file_put_contents($root.'/outside.json', '{}');
file_put_contents($root.'/outside.quarantine', 'outside');

$quarantine = new FilesystemQuarantine($directory);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($quarantine, $directory, $root, $id): void {
    file_put_contents($directory.'/'.$id.'.json', $input);
    file_put_contents($directory.'/'.$id.'.quarantine', 'kept');

    $file = $quarantine->file($id);

    if (!$file instanceof QuarantinedFile || $id !== $file->id || $file->size < 0) {
        throw new LogicException('A record of the quarantine was not read as a file.');
    }

    if ([] === $quarantine->files(10)) {
        throw new LogicException('A record of the quarantine was not listed.');
    }

    if ($input !== $id && ($quarantine->file($input) instanceof QuarantinedFile || $quarantine->delete($input))) {
        throw new LogicException('An identifier the quarantine never gave named a file.');
    }

    if (!is_file($root.'/outside.json') || !is_file($root.'/outside.quarantine') || !is_file($directory.'/'.$id.'.quarantine')) {
        throw new LogicException('A file was deleted by an identifier that does not name it.');
    }
});
$config->setMaxLen(1024);

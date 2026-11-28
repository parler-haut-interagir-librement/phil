<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use ZipArchive;

/**
 * The temporary files of a test, in a folder of their own under the system's temporary directory, removed after it.
 */
trait TemporaryFiles
{
    private ?string $temporaryFolder = null;

    protected function folder(): string
    {
        if (null === $this->temporaryFolder) {
            $this->temporaryFolder = sys_get_temp_dir().'/phil-spreadsheet-test-'.bin2hex(random_bytes(6));
            mkdir($this->temporaryFolder);
        }

        return $this->temporaryFolder;
    }

    protected function path(string $name): string
    {
        return $this->folder().'/'.$name;
    }

    /**
     * A file holding the bytes given.
     */
    protected function file(string $name, string $bytes): string
    {
        $path = $this->path($name);
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * An archive holding the entries given, by name, in their order.
     *
     * @param array<string, string> $entries
     */
    protected function zip(string $name, array $entries): string
    {
        $path = $this->path($name);
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $bytes) {
            $zip->addFromString($entry, $bytes);
        }

        $zip->close();

        return $path;
    }

    /**
     * The names of the files in the folder, sorted.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        $files = glob($this->folder().'/*');

        return array_map(basename(...), false === $files ? [] : $files);
    }

    #[After]
    protected function removeTemporaryFiles(): void
    {
        if (null === $this->temporaryFolder) {
            return;
        }

        $files = glob($this->temporaryFolder.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->temporaryFolder);
        $this->temporaryFolder = null;
    }
}

<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit\Fixture;

use RuntimeException;
use ZipArchive;

/**
 * An EPUB file opened for a test: its entries in archive order, their bytes, compression and modification time.
 */
final readonly class Archive
{
    /**
     * @param array<string, array{bytes: string, method: int, mtime: int}> $entries
     */
    private function __construct(public array $entries)
    {
    }

    public static function read(string $bytes): self
    {
        $path = tempnam(sys_get_temp_dir(), 'epub-test');

        if (false === $path) {
            throw new RuntimeException('No temporary file.');
        }

        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        $zip->open($path);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);

            if (false === $stat) {
                throw new RuntimeException('No entry.');
            }

            $bytes = $zip->getFromIndex($index);

            if (false === $bytes) {
                throw new RuntimeException('No bytes.');
            }

            $entries[$stat['name']] = ['bytes' => $bytes, 'method' => $stat['comp_method'], 'mtime' => $stat['mtime']];
        }

        $zip->close();
        unlink($path);

        return new self($entries);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    public function file(string $name): string
    {
        return $this->entries[$name]['bytes'] ?? throw new RuntimeException(\sprintf('No entry "%s".', $name));
    }

    /**
     * The body of an XHTML document of the package, between its `<body>` tags.
     */
    public function body(string $name): string
    {
        if (1 !== preg_match('#<body[^>]*>(.*)</body>#s', $this->file('EPUB/'.$name), $body)) {
            throw new RuntimeException(\sprintf('No body in "%s".', $name));
        }

        return trim($body[1]);
    }
}

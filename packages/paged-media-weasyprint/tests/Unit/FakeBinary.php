<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Unit;

use Symfony\Component\Filesystem\Filesystem;

/**
 * A stand-in for the `weasyprint` command: a shell script that keeps its arguments and its input, and does what the
 * test wrote. A directory of its own holds them, and the files a test places.
 */
final readonly class FakeBinary
{
    public string $directory;

    public string $path;

    public function __construct(string $script)
    {
        $this->directory = sys_get_temp_dir().'/phil-weasyprint-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->path = $this->directory.'/weasyprint';
        file_put_contents($this->path, \sprintf("#!/bin/sh\nfor a in \"\$@\"; do printf '%%s\\n' \"\$a\"; done > '%s/arguments'\ncat > '%s/input'\n%s\n", $this->directory, $this->directory, $script));
        chmod($this->path, 0o700);
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return explode("\n", rtrim((string) file_get_contents($this->directory.'/arguments'), "\n"));
    }

    public function input(): string
    {
        return (string) file_get_contents($this->directory.'/input');
    }

    public function remove(): void
    {
        new Filesystem()->remove($this->directory);
    }
}

<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Tests\Unit;

use Symfony\Component\Filesystem\Filesystem;

/**
 * A stand-in for `lou_translate`: a shell script that keeps its arguments, its input and the table path it was given,
 * then does what the test wrote. A directory of its own holds them.
 */
final readonly class FakeBinary
{
    public string $directory;

    public string $path;

    public function __construct(string $script)
    {
        $this->directory = sys_get_temp_dir().'/phil-liblouis-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->path = $this->directory.'/lou_translate';
        file_put_contents($this->path, \sprintf(
            "#!/bin/sh\nfor a in \"\$@\"; do printf '%%s\\n' \"\$a\"; done > '%1\$s/arguments'\nprintf '%%s' \"\$LOUIS_TABLEPATH\" > '%1\$s/tablepath'\ncat > '%1\$s/input'\n%2\$s\n",
            $this->directory,
            $script,
        ));
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

    public function tablePath(): string
    {
        return (string) file_get_contents($this->directory.'/tablepath');
    }

    public function ran(): bool
    {
        return is_file($this->directory.'/arguments');
    }

    public function remove(): void
    {
        new Filesystem()->remove($this->directory);
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem;

use Override;
use Phil\CommerceProductTemplateBundle\Application\Port\ImageStore;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use RuntimeException;

/**
 * `ImageStore` in a directory: each path is a file under it. A path that could leave the directory is refused before
 * any file is touched, and an image is written to a temporary file then renamed, so a reader never sees half of it.
 * The directories it creates take `$directoryMode`, and each file `$fileMode` when one is given, before it is renamed.
 */
final readonly class DirectoryImageStore implements ImageStore
{
    public function __construct(
        private string $directory,
        private int $directoryMode = 0o775,
        private ?int $fileMode = null,
    ) {
    }

    #[Override]
    public function read(string $path): ?string
    {
        $file = $this->file($path);
        $bytes = is_file($file) ? @file_get_contents($file) : false;

        return false === $bytes ? null : $bytes;
    }

    #[Override]
    public function has(string $path): bool
    {
        return is_file($this->file($path));
    }

    #[Override]
    public function write(string $path, string $bytes): void
    {
        $file = $this->file($path);
        $directory = \dirname($file);

        if (!is_dir($directory)) {
            // Another writer may create it at the same moment: whether it exists afterwards is what counts.
            @mkdir($directory, $this->directoryMode, true);
        }

        if (!is_dir($directory)) {
            throw new RuntimeException(\sprintf('The directory of the image %s cannot be created.', $path));
        }

        $temporary = \sprintf('%s.%s.tmp', $file, sodium_bin2hex(random_bytes(8)));

        if (false === @file_put_contents($temporary, $bytes) || (null !== $this->fileMode && !@chmod($temporary, $this->fileMode)) || !@rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException(\sprintf('The image %s cannot be written.', $path));
        }
    }

    private function file(string $path): string
    {
        if (1 !== preg_match('#^(?!.*(?:^|/)\.{1,2}(?:/|$))[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$#D', $path) || \strlen($path) > 512) {
            throw InvalidValue::because(\sprintf('"%s" is not a path of the store of images.', $path));
        }

        return $this->directory.'/'.$path;
    }
}

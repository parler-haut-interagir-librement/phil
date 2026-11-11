<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Store;

use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use RuntimeException;

/**
 * A directory readable by its owner only, whose files are named by 32 random hexadecimal digits and a suffix: a name
 * from a message is checked against that shape before it is joined to the directory.
 *
 * @internal
 */
final readonly class PrivateDirectory
{
    public function __construct(
        private string $path,
        private string $suffix,
    ) {
    }

    /**
     * A new identifier, and the path of its file, the directory created when it is missing.
     *
     * @return array{string, string}
     */
    public function create(): array
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0o700, true);
        }

        // Checked again rather than trusted to mkdir(): another worker may have created it at the same moment.
        if (!is_dir($this->path)) {
            throw new RuntimeException(\sprintf('The directory "%s" cannot be created.', $this->path));
        }

        $id = sodium_bin2hex(random_bytes(16));

        return [$id, $this->file($id)];
    }

    /**
     * The path of the identifier's file.
     *
     * @throws JobUnavailable when the identifier is not 32 hexadecimal digits
     */
    public function file(string $id): string
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw JobUnavailable::of($id, 'not an identifier of the store');
        }

        return $this->path.'/'.$id.$this->suffix;
    }

    public function remove(string $id): void
    {
        $file = $this->file($id);

        if (is_file($file)) {
            unlink($file);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Image;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Symfony\Component\Process\Process;

/**
 * Encodes a lossless PNG to WebP with `cwebp` or to AVIF with `avifenc`, the reference encoders of each format, which
 * compress better than GD at the same quality. The binary runs without a shell, on two files of a private temporary
 * directory, for a minute at most.
 */
final readonly class BinaryEncoder
{
    public const array BINARIES = ['webp' => 'cwebp', 'avif' => 'avifenc'];

    private function __construct(
        public string $type,
        public string $binary,
    ) {
    }

    /**
     * @param string $binary the path of `cwebp` for `webp`, of `avifenc` for `avif`
     */
    public static function of(string $type, string $binary): self
    {
        self::name($type);

        if (!is_file($binary) || !is_executable($binary)) {
            throw InvalidMedia::because(\sprintf('"%s" is not an executable file.', $binary));
        }

        return new self($type, $binary);
    }

    /**
     * The type's binary found in the directories of a `PATH` — the process's by default —, or `null`.
     */
    public static function find(string $type, ?string $path = null): ?self
    {
        $name = self::name($type);
        $environment = getenv('PATH');
        $path ??= false === $environment ? '' : $environment;

        foreach (explode(\PATH_SEPARATOR, $path) as $directory) {
            $binary = $directory.'/'.$name;

            if ('' !== $directory && is_file($binary) && is_executable($binary)) {
                return new self($type, $binary);
            }
        }

        return null;
    }

    /**
     * @param int<1, 100> $quality
     */
    public function encode(string $png, int $quality): string
    {
        $directory = sys_get_temp_dir().'/phil-media-encoding-'.sodium_bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $input = $directory.'/in.png';
        $output = $directory.'/out.'.$this->type;
        file_put_contents($input, $png);

        try {
            $process = new Process('webp' === $this->type
                ? [$this->binary, '-quiet', '-q', "{$quality}", '-alpha_q', '100', '-metadata', 'none', $input, '-o', $output]
                : [$this->binary, '-q', "{$quality}", '-s', '6', '--ignore-exif', '--ignore-xmp', $input, $output]);
            $process->run();
            $encoded = $process->isSuccessful() && is_file($output) ? file_get_contents($output) : false;

            if (false === $encoded || '' === $encoded) {
                throw InvalidMedia::because(\sprintf('%s could not encode the image: %s', basename($this->binary), trim($process->getErrorOutput())));
            }

            return $encoded;
        } finally {
            foreach ([$input, $output] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($directory);
        }
    }

    private static function name(string $type): string
    {
        return self::BINARIES[$type] ?? throw InvalidMedia::because(\sprintf('A binary encodes webp or avif; not "%s".', $type));
    }
}

<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;

/**
 * A source stream that gives its bytes, then nothing more without ever ending, as a remote stream that stops.
 */
final class StallingStream
{
    public const string SCHEME = 'phil-upload-scan-stalling';

    /** @var resource|null */
    public $context;

    private string $bytes = '';

    /**
     * @return resource
     */
    public static function open(string $bytes): mixed
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }

        $stream = fopen(self::SCHEME.'://'.bin2hex($bytes), 'r');

        if (!\is_resource($stream)) {
            throw new LogicException('The stalling stream did not open.');
        }

        return $stream;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->bytes = (string) hex2bin(substr($path, \strlen(self::SCHEME.'://')));

        return true;
    }

    public function stream_read(int $count): string
    {
        $bytes = substr($this->bytes, 0, $count);
        $this->bytes = substr($this->bytes, \strlen($bytes));

        return $bytes;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /**
     * A rewind at the start, which the scanners ask for first.
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        return 0 === $offset && \SEEK_SET === $whence;
    }

    public function stream_tell(): int
    {
        return 0;
    }
}

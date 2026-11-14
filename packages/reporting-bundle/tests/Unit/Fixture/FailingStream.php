<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use RuntimeException;

/**
 * A stream that fails as a full disk or a broken source does: `failing://flush` accepts what is written but cannot
 * flush it, `failing://read` throws on the first read.
 */
final class FailingStream
{
    public const string SCHEME = 'failing';

    /** @var resource|null */
    public $context;

    private string $mode = '';

    public static function register(): void
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public function stream_open(string $path): bool
    {
        $this->mode = substr($path, \strlen(self::SCHEME) + 3);

        return true;
    }

    public function stream_write(string $bytes): int
    {
        return \strlen($bytes);
    }

    public function stream_read(): string
    {
        throw new RuntimeException('The source broke.');
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_flush(): bool
    {
        return 'flush' !== $this->mode;
    }
}

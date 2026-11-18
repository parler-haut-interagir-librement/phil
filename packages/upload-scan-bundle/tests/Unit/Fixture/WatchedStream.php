<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Closure;

/**
 * A stream of a few bytes that calls a closure the first time it is read: what is on the disk while a copy of it is
 * under way.
 */
final class WatchedStream
{
    public const string SCHEME = 'phil-watched-upload';

    /** @var resource|null */
    public $context;

    public static ?Closure $watch = null;

    private bool $read = false;

    /**
     * @return resource
     */
    public static function open(Closure $watch): mixed
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }

        self::$watch = $watch;
        $stream = fopen(self::SCHEME.'://bytes', 'r');
        \assert(\is_resource($stream));

        return $stream;
    }

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_read(): string
    {
        if ($this->read) {
            return '';
        }

        $this->read = true;
        (self::$watch ?? static function (): void {})();

        return 'watched bytes';
    }

    public function stream_eof(): bool
    {
        return $this->read;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}

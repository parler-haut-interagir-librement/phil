<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Support;

/**
 * A stream that gives its bytes three at a time, or as many as `$size` says, as a pipe may: a sample is then split
 * between two reads.
 */
final class TrickleStream
{
    public const string PROTOCOL = 'phil-audio-trickle';

    public static string $bytes = '';

    public static int $size = 3;

    /** @var resource|null set by PHP for every stream wrapper */
    public $context;

    private int $position = 0;

    public static function open(string $bytes, int $size = 3): mixed
    {
        if (!\in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }

        self::$bytes = $bytes;
        self::$size = $size;

        return fopen(self::PROTOCOL.'://samples', 'r');
    }

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$bytes, $this->position, min(self::$size, $count));
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$bytes);
    }
}

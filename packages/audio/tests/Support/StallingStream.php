<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Support;

/**
 * A stream that has nothing to give yet and is not at its end, as a pipe whose writer is slow: a read gives no bytes.
 */
final class StallingStream
{
    public const string PROTOCOL = 'phil-audio-stalling';

    /** @var resource|null set by PHP for every stream wrapper */
    public $context;

    public static function open(): mixed
    {
        if (!\in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }

        return fopen(self::PROTOCOL.'://samples', 'r');
    }

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_read(): string
    {
        return '';
    }

    public function stream_eof(): bool
    {
        return false;
    }
}

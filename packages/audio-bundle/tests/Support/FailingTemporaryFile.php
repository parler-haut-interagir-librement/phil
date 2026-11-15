<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Support;

/**
 * A directory whose files accept a creation, a mode and a deletion, then refuse the writing or the flushing, as a full
 * disk does: `$failing` is "write" or "flush".
 */
final class FailingTemporaryFile
{
    public const string PROTOCOL = 'phil-audio-failing';

    public static string $failing = 'write';

    /** @var list<string> the paths deleted */
    public static array $deleted = [];

    /** @var resource|null set by PHP for every stream wrapper */
    public $context;

    public static function directory(string $failing): string
    {
        if (!\in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }

        self::$failing = $failing;
        self::$deleted = [];

        return self::PROTOCOL.'://disk';
    }

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_write(string $data): int|false
    {
        return 'write' === self::$failing ? false : \strlen($data);
    }

    public function stream_flush(): bool
    {
        return 'write' === self::$failing;
    }

    public function stream_metadata(): bool
    {
        return true;
    }

    public function unlink(string $path): bool
    {
        self::$deleted[] = $path;

        return true;
    }
}

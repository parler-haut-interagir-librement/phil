<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Support;

/**
 * A stream wrapper, `refusing://`, whose streams open and refuse every byte written to them: a full disk.
 */
final class RefusingStream
{
    public const string SCHEME = 'refusing';

    /** @var resource|null set by PHP */
    public $context;

    public static function register(): void
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_write(): int
    {
        return 0;
    }
}

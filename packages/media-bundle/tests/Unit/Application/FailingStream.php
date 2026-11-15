<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use RuntimeException;

/**
 * A stream wrapper whose streams give their first bytes, then fail: the read returns `false` or throws.
 */
final class FailingStream
{
    /**
     * `false` or `throw`: how the second read fails.
     */
    public static string $mode = 'false';

    /**
     * @var resource|null
     */
    public $context;

    private bool $started = false;

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_read(): false|string
    {
        if (!$this->started) {
            $this->started = true;

            return 'abc';
        }

        if ('throw' === self::$mode) {
            throw new RuntimeException('The stream broke.');
        }

        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}

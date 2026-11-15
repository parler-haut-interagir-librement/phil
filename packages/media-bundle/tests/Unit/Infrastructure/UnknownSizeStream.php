<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

/**
 * A stream wrapper whose streams cannot tell their size: `fstat()` returns `false`.
 */
final class UnknownSizeStream
{
    /**
     * @var resource|null
     */
    public $context;

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
        return true;
    }

    public function stream_stat(): false
    {
        return false;
    }
}

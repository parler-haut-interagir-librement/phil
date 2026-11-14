<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Fixture;

/**
 * A stream that takes two writes, then refuses every other one, as a client that goes away: registered as
 * `two-lines://`, its writes kept in `$taken`.
 */
final class TwoLineStream
{
    /** @var list<string> */
    public static array $taken = [];

    /** How many of the first writes are refused, whatever was taken. */
    public static int $refuse = 0;

    /** @var resource|null */
    public $context;

    public function stream_open(): bool
    {
        self::$taken = [];

        return true;
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    public function stream_write(string $data): int
    {
        if (self::$refuse > 0) {
            --self::$refuse;

            return 0;
        }

        if (\count(self::$taken) >= 2) {
            return 0;
        }

        self::$taken[] = $data;

        return \strlen($data);
    }
}

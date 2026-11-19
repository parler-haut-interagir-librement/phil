<?php

declare(strict_types=1);

namespace Phil\UploadScan\Internal;

use Closure;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\ScanResult;

/**
 * The input of the scanners and the warnings of PHP's stream functions, which the scanners turn into results.
 *
 * @internal
 */
final class Io
{
    public const string READ_BEFORE = 'The stream was read before the scan and cannot be rewound: give it to the scanner before reading it.';

    public const string UNREADABLE = 'The file cannot be read.';

    public const string TIMED_OUT = 'The file stopped coming: its stream timed out before its end.';

    public const string UNFINISHED = 'The file stopped before its end: its stream gave nothing more and did not end.';

    private function __construct()
    {
    }

    /**
     * The next chunk of the stream, at most the bytes asked for; `''` only at the end of the stream; an error result
     * when the stream fails, times out, or gives nothing without having ended. A stream that stops is never taken for
     * a file that ends, so that a scan never answers clean for the start of a file.
     *
     * @param resource    $stream
     * @param int<1, max> $bytes
     */
    public static function chunk(mixed $stream, int $bytes): string|ScanResult
    {
        $chunk = self::quietly(static fn (): string|false => fread($stream, $bytes));

        if (self::timedOut($stream)) {
            return ScanResult::error(self::TIMED_OUT);
        }

        if (false === $chunk) {
            return ScanResult::error(self::UNREADABLE);
        }

        if ('' === $chunk && !feof($stream)) {
            return ScanResult::error(self::UNFINISHED);
        }

        return $chunk;
    }

    /**
     * @return resource
     *
     * @throws InvalidArgument when the value is not an open stream
     */
    public static function stream(mixed $stream): mixed
    {
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw InvalidArgument::because(\sprintf('A scanner reads an open stream, not %s.', get_debug_type($stream)));
        }

        return $stream;
    }

    /**
     * Whether the stream stands at its start, rewound when it can be: a stream read before the scan, to sniff its
     * type or hash it, is scanned whole, never from where that read stopped. A stream that cannot be rewound stands
     * at its start only when nothing was read from it.
     *
     * @param resource $stream
     */
    public static function atStart(mixed $stream): bool
    {
        if (stream_get_meta_data($stream)['seekable']) {
            return self::quietly(static fn (): bool => rewind($stream));
        }

        return 0 === ftell($stream);
    }

    /**
     * Whether the last read of the stream waited past its timeout; a stream without a timeout, such as `php://temp`,
     * states none in its metadata.
     *
     * @param resource $stream
     */
    public static function timedOut(mixed $stream): bool
    {
        return self::flag(stream_get_meta_data($stream), 'timed_out');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function flag(array $metadata, string $name): bool
    {
        return true === ($metadata[$name] ?? false);
    }

    /**
     * A local file opened for reading, or `null` when the path is not a readable file.
     *
     * @return resource|null
     */
    public static function open(string $path): mixed
    {
        if (!is_file($path)) {
            return null;
        }

        $stream = self::quietly(static fn (): mixed => fopen($path, 'r'));

        return \is_resource($stream) ? $stream : null;
    }

    /**
     * Runs a stream function with its warnings silenced: a broken pipe or a refused connection is read from what the
     * function returns.
     *
     * @template T
     *
     * @param Closure(): T $io
     *
     * @return T
     */
    public static function quietly(Closure $io): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $io();
        } finally {
            restore_error_handler();
        }
    }
}

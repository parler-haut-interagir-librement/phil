<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;
use Throwable;

/**
 * A read that did not end: the host did not answer, stayed silent too long, took longer than the whole duration, sent
 * headers past their bytes or their count, or the connection broke.
 *
 * The message shows the address without its user information, query and fragment. After a transport error, it adds
 * that error's message with each address in it shown the same way, and the error is not kept as the previous
 * exception: the HTTP client writes the whole address in it, which a log would keep.
 */
final class FetchFailed extends RuntimeException implements SafeHttpException
{
    public static function transport(string $address, Throwable $previous): self
    {
        return new self(\sprintf('"%s" could not be read: %s', ShownAddress::of($address), ShownAddress::in($previous->getMessage())));
    }

    public static function outOfTime(string $address): self
    {
        return new self(\sprintf('"%s" took longer than the whole duration allowed.', ShownAddress::of($address)));
    }

    public static function headersTooLarge(string $address, int $maxHeaderBytes, int $maxHeaders): self
    {
        return new self(\sprintf('"%s" sent headers past %d bytes or %d headers.', ShownAddress::of($address), $maxHeaderBytes, $maxHeaders));
    }
}

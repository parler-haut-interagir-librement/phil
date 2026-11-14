<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;
use Throwable;

/**
 * A read that did not end: the host did not answer, stayed silent too long, took longer than the whole duration, sent
 * headers past their bytes or their count, or the connection broke.
 */
final class FetchFailed extends RuntimeException implements SafeHttpException
{
    public static function transport(string $address, Throwable $previous): self
    {
        return new self(\sprintf('"%s" could not be read: %s', mb_substr($address, 0, 100), $previous->getMessage()), 0, $previous);
    }

    public static function outOfTime(string $address): self
    {
        return new self(\sprintf('"%s" took longer than the whole duration allowed.', mb_substr($address, 0, 100)));
    }

    public static function headersTooLarge(string $address, int $maxHeaderBytes, int $maxHeaders): self
    {
        return new self(\sprintf('"%s" sent headers past %d bytes or %d headers.', mb_substr($address, 0, 100), $maxHeaderBytes, $maxHeaders));
    }
}

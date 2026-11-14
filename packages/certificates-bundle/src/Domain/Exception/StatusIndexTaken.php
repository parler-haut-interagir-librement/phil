<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A certificate the storage refused because another, stored in the meantime, holds its status index or its code. The
 * storage is ready for another attempt, which takes the next index.
 */
final class StatusIndexTaken extends RuntimeException implements CertificatesException
{
    public static function at(int $index, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The status index %d was taken by another certificate in the meantime.', $index), 0, $previous);
    }
}

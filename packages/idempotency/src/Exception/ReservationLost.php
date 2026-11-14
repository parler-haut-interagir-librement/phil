<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use RuntimeException;

/**
 * The run no longer holds its key: its reservation expired, and another run reserved the key since, or the key was
 * released. Its outcome was not written, so that the newer reservation keeps its own.
 *
 * The run that receives it has done its work, but its response is not the one the key replays: a reservation shorter
 * than the slowest run is the usual cause.
 */
final class ReservationLost extends RuntimeException implements IdempotencyException
{
    public static function toAnotherRun(): self
    {
        return new self('The reservation of this idempotency key expired and the key is no longer held by this run: its outcome was not kept.');
    }
}

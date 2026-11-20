<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use RuntimeException;
use Throwable;

/**
 * At least one module failed to erase. The others ran, and their reports are kept here, so the retry knows what
 * is already done and the person is never told an erasure completed that did not.
 *
 * The failures of the modules stay in `$failures` and are never chained as the previous exception: the message of an
 * eraser's exception can quote the person's data, such as a duplicate-key error naming an address, and a chained
 * exception follows this one into logs and Messenger's retry and failure transports.
 */
final class ErasureIncomplete extends RuntimeException implements PrivacyError
{
    /**
     * @param list<ErasureReport>                $completed
     * @param non-empty-array<string, Throwable> $failures  by module name
     */
    private function __construct(
        string $message,
        public readonly array $completed,
        public readonly array $failures,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<ErasureReport>                $completed
     * @param non-empty-array<string, Throwable> $failures  by module name
     */
    public static function after(array $completed, array $failures): self
    {
        return new self(
            \sprintf('Erasure failed in module(s) %s; retry it, erasers are idempotent.', implode(', ', array_keys($failures))),
            $completed,
            $failures,
        );
    }
}

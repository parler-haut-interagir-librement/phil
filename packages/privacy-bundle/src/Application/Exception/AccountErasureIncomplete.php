<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * The erasure of an account deletion failed in at least one module, and Messenger retries the message.
 *
 * It carries the identifier of the request and the names of the modules, nothing else, and no previous exception:
 * Messenger keeps the message of a failure, and that of every exception chained to it, in the `ErrorDetailsStamp` of
 * the envelope sent to the retry and failure transports, and in its logs.
 */
final class AccountErasureIncomplete extends RuntimeException implements PrivacyError
{
    /**
     * @param non-empty-list<string> $modules
     */
    public function __construct(
        public readonly string $deletionId,
        public readonly array $modules,
    ) {
        parent::__construct(\sprintf('Account deletion %s: the erasure failed in module(s) %s; it is retried.', $deletionId, implode(', ', $modules)));
    }

    public static function of(string $deletionId, ErasureIncomplete $erasure): self
    {
        return new self($deletionId, array_map(strval(...), array_keys($erasure->failures)));
    }
}

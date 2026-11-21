<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Message;

/**
 * Runs one module's part of an erasure request: identifiers only, the person read again by the worker.
 */
final readonly class EraseModule
{
    /** The type of its outbox record. */
    public const string TYPE = 'phil_privacy.erase_module';

    public function __construct(
        public string $requestId,
        public string $module,
    ) {
    }
}

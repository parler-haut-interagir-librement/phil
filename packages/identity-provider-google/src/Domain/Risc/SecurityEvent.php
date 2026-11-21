<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Domain\Risc;

/**
 * One event of a security event token: its type, the subject it is about when the subject is an issuer and a subject
 * identifier — `iss-sub` in RISC, `iss_sub` in the Shared Signals Framework —, and the `reason` or `state` it carries.
 * The subject is null when the event names it otherwise, such as by a token the receiver never kept.
 */
final readonly class SecurityEvent
{
    public function __construct(
        public SecurityEventType $type,
        public ?string $subjectIssuer = null,
        public ?string $subject = null,
        public ?string $reason = null,
        public ?string $state = null,
    ) {
    }
}

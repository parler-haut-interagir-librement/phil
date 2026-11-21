<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

/**
 * Why a module needs attention: a closed list, so that the reason names no value of the application and no exception.
 */
enum AttentionReason: string
{
    /** Messenger tried the module's message for the last time. */
    case RetriesExhausted = 'retries_exhausted';

    /** The request names a module that no eraser declares any more. */
    case UnknownModule = 'unknown_module';

    /** The person of a request made without an account can no longer be found: the request it came from is gone. */
    case SubjectUnavailable = 'subject_unavailable';
}

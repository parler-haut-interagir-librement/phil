<?php

declare(strict_types=1);

namespace Phil\Idempotency\Contract;

use Phil\Idempotency\PayloadFingerprint;

/**
 * A Messenger message that names the fields of its intention: two deliveries under one key with the same fingerprint are one
 * intention, and a key reused with another fingerprint is refused.
 *
 * The fingerprint covers what the handler acts on — identifiers, an amount, a state — and leaves out what may differ
 * between two dispatches of one intention, such as the moment it was dispatched.
 */
interface FingerprintedMessage
{
    public function idempotencyFingerprint(): PayloadFingerprint;
}

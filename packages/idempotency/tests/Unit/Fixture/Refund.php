<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Fixture;

use Phil\Idempotency\Contract\FingerprintedMessage;
use Phil\Idempotency\PayloadFingerprint;

/**
 * A refund whose intention is its order: the note may differ between two dispatches.
 */
final readonly class Refund implements FingerprintedMessage
{
    public function __construct(
        public string $order,
        public string $note,
    ) {
    }

    public function idempotencyFingerprint(): PayloadFingerprint
    {
        return PayloadFingerprint::ofParts($this->order);
    }
}

<?php

declare(strict_types=1);

namespace Phil\Idempotency;

/**
 * The rule, as a pure function of what the store holds and what the operation carries.
 *
 * | Store holds | Fingerprint | Outcome |
 * |---|---|---|
 * | nothing | any | `Proceed` |
 * | a record | different | `Reused` |
 * | in progress | same | `InProgress` |
 * | failed | same | `Proceed` |
 * | completed | same | `Replay` |
 */
final readonly class IdempotencyDecision
{
    private function __construct(
        public IdempotencyOutcome $outcome,
        public ?IdempotencyRecord $existing,
    ) {
    }

    public static function for(?IdempotencyRecord $existing, PayloadFingerprint $fingerprint): self
    {
        if (!$existing instanceof IdempotencyRecord) {
            return new self(IdempotencyOutcome::Proceed, null);
        }

        // The payload is compared before the state: a different payload is refused whether the first run has
        // finished, is running or has failed, so a caller learns of its mistake on the first retry.
        if (!$existing->fingerprint->equals($fingerprint)) {
            return new self(IdempotencyOutcome::Reused, $existing);
        }

        return new self(match ($existing->state) {
            IdempotencyState::InProgress => IdempotencyOutcome::InProgress,
            IdempotencyState::Failed => IdempotencyOutcome::Proceed,
            IdempotencyState::Completed => IdempotencyOutcome::Replay,
        }, $existing);
    }

    /**
     * The response to return on `Replay`, `null` otherwise.
     */
    public function replay(): ?StoredResponse
    {
        return IdempotencyOutcome::Replay === $this->outcome ? $this->existing?->response : null;
    }
}

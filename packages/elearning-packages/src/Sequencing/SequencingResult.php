<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The answer of the sequencer to a request: the outcome, the activity to launch when one is delivered, the reason of a
 * refusal, and the state to keep — which a refusal may also have changed, when the request ended the current attempt
 * before it found nothing to deliver.
 */
final readonly class SequencingResult
{
    public function __construct(
        public Outcome $outcome,
        public SequencingState $state,
        public ?string $activity = null,
        public ?string $reason = null,
    ) {
    }

    public function isDelivered(): bool
    {
        return Outcome::Delivered === $this->outcome;
    }
}

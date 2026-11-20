<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;

/**
 * One module's part of an erasure request, kept under the unique pair of the request and the module.
 *
 * The idempotency key is stable for the pair: it is the key of the module's outbox record, and the key an external
 * service receives, so that a message delivered twice erases once. It is made of the request's random identifier and
 * the module's name, never of the person.
 */
final readonly class ErasureStep
{
    /**
     * @param array<string, ErasureEffect> $effects  what the module did, by store, once it completed
     * @param int                          $attempts the attempts that failed and gave the step back
     */
    public function __construct(
        public string $requestId,
        public string $module,
        public StepState $state,
        public int $attempts,
        public array $effects,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $completedAt = null,
        public ?AttentionReason $reason = null,
    ) {
    }

    /**
     * A new step, pending.
     */
    public static function pending(string $requestId, string $module, DateTimeImmutable $now): self
    {
        return new self($requestId, $module, StepState::Pending, 0, [], $now);
    }

    /**
     * The idempotency key of the pair of a request and a module.
     */
    public static function keyOf(string $requestId, string $module): string
    {
        return $requestId.':'.$module;
    }

    public function idempotencyKey(): string
    {
        return self::keyOf($this->requestId, $this->module);
    }
}

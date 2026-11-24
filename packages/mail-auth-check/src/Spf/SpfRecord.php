<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

/**
 * An SPF record read by `SpfParser`: its mechanisms in order and its `redirect` and `exp` modifiers. Other modifiers
 * are ignored, as RFC 7208 asks.
 */
final readonly class SpfRecord
{
    /**
     * @param list<SpfMechanism> $mechanisms
     * @param ?string            $redirect    the domain of `redirect=`, in lowercase
     * @param ?string            $explanation the domain of `exp=`, in lowercase
     */
    public function __construct(
        public array $mechanisms,
        public ?string $redirect = null,
        public ?string $explanation = null,
    ) {
    }

    /**
     * The first `all` mechanism; the mechanisms after it are never evaluated, and `redirect` is ignored when there is
     * one.
     */
    public function all(): ?SpfMechanism
    {
        foreach ($this->mechanisms as $mechanism) {
            if ('all' === $mechanism->name) {
                return $mechanism;
            }
        }

        return null;
    }
}

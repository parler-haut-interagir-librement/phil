<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

/**
 * What a leaf condition answers: whether it holds, a stable reason code an application may translate, and the details
 * that explain it, such as the value a fact held.
 */
final readonly class Verdict
{
    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        public bool $passed,
        public string $reason,
        public array $details = [],
    ) {
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function pass(string $reason, array $details = []): self
    {
        return new self(true, $reason, $details);
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public static function fail(string $reason, array $details = []): self
    {
        return new self(false, $reason, $details);
    }
}

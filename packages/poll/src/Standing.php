<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * Where one option finished: its place, 1 for the first, shared by the options tied with it — two options tied first
 * are both first and the next one is third —, and the score that placed it, whose meaning is the counting method's.
 */
final readonly class Standing
{
    public function __construct(
        public string $option,
        public int $score,
        public int $place,
    ) {
    }
}

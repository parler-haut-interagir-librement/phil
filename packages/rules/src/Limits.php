<?php

declare(strict_types=1);

namespace Phil\Rules;

use Phil\Rules\Exception\InvalidLimit;

/**
 * The bounds of a rule: the bytes of its JSON form, how deeply its conditions nest, how many it holds, and how many
 * leaves one evaluation may evaluate. Past a bound, the library throws `LimitExceeded`; nothing in a rule can loop.
 */
final readonly class Limits
{
    /** @var int<1, 1048576> */
    public int $maxBytes;

    /** @var int<1, 64> */
    public int $maxDepth;

    /** @var int<1, 10000> */
    public int $maxNodes;

    /** @var int<1, 100000> */
    public int $maxEvaluations;

    public function __construct(int $maxBytes = 65536, int $maxDepth = 16, int $maxNodes = 256, int $maxEvaluations = 256)
    {
        if ($maxBytes < 1 || $maxBytes > 1048576) {
            throw InvalidLimit::outOfRange('maxBytes', $maxBytes, 1, 1048576);
        }

        if ($maxDepth < 1 || $maxDepth > 64) {
            throw InvalidLimit::outOfRange('maxDepth', $maxDepth, 1, 64);
        }

        if ($maxNodes < 1 || $maxNodes > 10000) {
            throw InvalidLimit::outOfRange('maxNodes', $maxNodes, 1, 10000);
        }

        if ($maxEvaluations < 1 || $maxEvaluations > 100000) {
            throw InvalidLimit::outOfRange('maxEvaluations', $maxEvaluations, 1, 100000);
        }

        $this->maxBytes = $maxBytes;
        $this->maxDepth = $maxDepth;
        $this->maxNodes = $maxNodes;
        $this->maxEvaluations = $maxEvaluations;
    }
}

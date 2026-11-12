<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Override;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Symfony\Component\HttpFoundation\Request;

/**
 * A limiter of a fixed capacity per key, in memory, that records what it was asked.
 */
final class ScriptedLimiter implements Limiter
{
    /** @var list<array{string, int}> */
    public array $calls = [];

    /** @var array<string, int> */
    private array $used = [];

    public function __construct(
        private readonly ?int $limit = 3,
        private readonly int $resetAfter = 60,
    ) {
    }

    #[Override]
    public function consume(string $key, int $amount, Request $request): Decision
    {
        $this->calls[] = [$key, $amount];

        if (null === $this->limit) {
            return Decision::accepted(null, 0, 0);
        }

        $used = ($this->used[$key] ?? 0) + $amount;

        if ($used > $this->limit) {
            return Decision::refused($this->limit, $this->limit - ($this->used[$key] ?? 0), $this->resetAfter, $this->resetAfter);
        }

        $this->used[$key] = $used;

        return Decision::accepted($this->limit, $this->limit - $used, $this->resetAfter, 60);
    }
}

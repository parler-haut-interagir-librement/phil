<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * The clock of a request another request overtakes: the first time it is read, the other request runs to its end.
 * `Conversations` reads it first once it found no direct conversation, so that the other one starts it in between.
 */
final class RacingClock implements ClockInterface
{
    private ?Closure $other;

    public function __construct(
        Closure $other,
        private readonly ClockInterface $inner = new MockClock('2026-11-13 10:00:00 UTC'),
    ) {
        $this->other = $other;
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        $other = $this->other;
        $this->other = null;

        if ($other instanceof Closure) {
            $other();
        }

        return $this->inner->now();
    }
}

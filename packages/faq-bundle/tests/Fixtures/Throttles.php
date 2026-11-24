<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Fixtures;

use Override;
use Phil\FaqBundle\Application\Contract\Throttle;
use Phil\FaqBundle\Application\Exception\TooManyRequests;

/**
 * A throttle that records its keys, and refuses once it has let `limit` through.
 */
final class Throttles implements Throttle
{
    /** @var list<string> */
    public array $keys = [];

    public function __construct(
        private readonly int $limit = \PHP_INT_MAX,
    ) {
    }

    #[Override]
    public function consume(string $key): void
    {
        if (\count($this->keys) >= $this->limit) {
            throw new TooManyRequests(7);
        }

        $this->keys[] = $key;
    }
}

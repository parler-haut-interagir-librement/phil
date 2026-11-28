<?php

declare(strict_types=1);

namespace Phil\Rules\Fact;

use Phil\Rules\Exception\MissingFact;

/**
 * The scope of one evaluation: the facts a caller passes, read by name only when a condition asks for them. A source
 * lives for one evaluation, or for the evaluations of one subject, never from one request to the next.
 */
interface FactSource
{
    public function has(string $name): bool;

    /**
     * @throws MissingFact when the scope holds no such fact
     */
    public function get(string $name): mixed;
}

<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

/**
 * The calls several fakes received, in the order they received them.
 */
final class CallJournal
{
    /** @var list<string> */
    public array $calls = [];

    public function add(string $call): void
    {
        $this->calls[] = $call;
    }
}

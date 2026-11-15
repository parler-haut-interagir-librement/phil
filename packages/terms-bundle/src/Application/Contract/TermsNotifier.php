<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Contract;

use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * Tells the members about the terms: that a material version was published and awaits their acceptance, and, on a
 * schedule, that an acceptance is still awaited or will soon lapse.
 */
interface TermsNotifier
{
    public function published(PolicyVersion $version): void;

    /**
     * @param non-empty-list<string> $members 500 at most
     */
    public function remind(string $policy, int $version, array $members): void;
}

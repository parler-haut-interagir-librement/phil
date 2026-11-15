<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Phil\TermsBundle\Application\Contract\MemberSource;

/**
 * The members listed, or as many numbered ones as asked.
 */
final readonly class ListedMembers implements MemberSource
{
    /**
     * @param list<string> $members
     */
    public function __construct(
        private array $members = [],
        private int $numbered = 0,
    ) {
    }

    #[Override]
    public function members(): iterable
    {
        yield from $this->members;

        for ($i = 1; $i <= $this->numbered; ++$i) {
            yield 'm'.$i;
        }
    }
}

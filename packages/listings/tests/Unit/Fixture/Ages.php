<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Listings\Contract\MemberAges;
use Phil\Listings\MemberId;

final class Ages implements MemberAges
{
    /** @var array<string, int> */
    public array $ages = [];

    public int $asked = 0;

    #[Override]
    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int
    {
        ++$this->asked;

        return $this->ages[$member->value] ?? null;
    }
}

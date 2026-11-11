<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit\Fixture;

use Override;
use Phil\Listings\Contract\BlockList;
use Phil\Listings\MemberId;

final class Blocks implements BlockList
{
    /** @var list<array{string, string}> */
    public array $pairs = [];

    #[Override]
    public function isBlocked(MemberId $one, MemberId $other): bool
    {
        return \in_array([$one->value, $other->value], $this->pairs, true) || \in_array([$other->value, $one->value], $this->pairs, true);
    }
}

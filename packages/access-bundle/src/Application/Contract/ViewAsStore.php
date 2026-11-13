<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Contract;

use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * Where the member an administrator views the site as is kept between requests: the session, by default.
 */
interface ViewAsStore
{
    public function get(): ?MemberId;

    public function set(?MemberId $member): void;
}

<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Contract;

use Phil\Url\Url;

/**
 * A check of a target before a link to it is saved: that it answers, that it is not on a private network.
 */
interface TargetCheck
{
    /**
     * @return string|null why the target is refused, none when it may be used
     */
    public function refusal(Url $target): ?string;
}

<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use Override;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Contract\RespondentAddresses;
use Symfony\Component\Mime\Address;

/**
 * The default: no account has an address, only the address a respondent gave is written to.
 */
final readonly class NoRespondentAddresses implements RespondentAddresses
{
    #[Override]
    public function addressOf(ResourceReference $account): ?Address
    {
        return null;
    }
}

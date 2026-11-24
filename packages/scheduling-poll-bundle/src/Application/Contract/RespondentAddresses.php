<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Symfony\Component\Mime\Address;

/**
 * Where to send the date chosen to a respondent who joined with their account and gave no address: the application
 * knows its members' addresses, the bundle keeps none of them. The default finds none, and such a respondent gets no
 * mail.
 */
interface RespondentAddresses
{
    public function addressOf(ResourceReference $account): ?Address;
}

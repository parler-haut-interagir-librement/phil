<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Message;

/**
 * The names and addresses of the respondents of the polls closed longer ago than the delay of the configuration are
 * to be erased: the message of the bundle's schedule.
 */
final readonly class EraseRespondents
{
}

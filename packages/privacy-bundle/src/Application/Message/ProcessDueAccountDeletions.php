<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Message;

/**
 * Looks for the account deletions whose delay is over and removes the expired exports, at each tick of the schedule.
 */
final readonly class ProcessDueAccountDeletions
{
}

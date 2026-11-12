<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Message;

/**
 * Check whether a watched flow stopped completing, or completes again. The day is read from the clock when the message is handled: the message carries nothing.
 */
final readonly class CheckBrokenFlows
{
}

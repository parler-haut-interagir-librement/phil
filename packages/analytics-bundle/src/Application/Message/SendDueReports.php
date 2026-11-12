<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Message;

/**
 * Send the analytics reports due today. The day is read from the clock when the message is handled: the message carries nothing.
 */
final readonly class SendDueReports
{
}

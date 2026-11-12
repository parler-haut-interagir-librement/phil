<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Message;

/**
 * Check whether the visits of a watched site spiked or dipped the day before. The day is read from the clock when the message is handled: the message carries nothing.
 */
final readonly class CheckTrafficAlerts
{
}

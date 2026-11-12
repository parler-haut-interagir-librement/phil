<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Message;

/**
 * Roll up the daily analytics counters past their retention. The day is read from the clock when the message is handled: the message carries nothing.
 */
final readonly class ApplyCounterRetention
{
}

<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\ValueObject;

use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\SendRun;

/**
 * When a send run's circuit breaker pauses it: once at least `minimum` messages were tried since the run started or
 * was last resumed, the share of attempts the transport refused, of messages sent that bounced, or of messages sent
 * reported as spam, above its threshold, in percent. Complaints are read first, then bounces, then transport failures.
 */
final readonly class BreakerPolicy
{
    /**
     * @throws InvalidNewsletterValue when the minimum is not positive or a rate is not between 0 and 100
     */
    public function __construct(
        public int $minimum = 100,
        public float $transportFailureRate = 10.0,
        public float $bounceRate = 5.0,
        public float $complaintRate = 0.3,
    ) {
        if ($minimum < 1) {
            throw InvalidNewsletterValue::because('The minimum of a circuit breaker is positive.');
        }

        foreach ([$transportFailureRate, $bounceRate, $complaintRate] as $rate) {
            if ($rate < 0.0 || $rate > 100.0) {
                throw InvalidNewsletterValue::because('A rate of a circuit breaker is a percentage, from 0 to 100.');
            }
        }
    }

    /**
     * Why the run should pause; `null` while it is under its thresholds.
     */
    public function tripped(SendRun $run): ?PauseReason
    {
        $sent = $run->sinceResume(SendRunCounter::Sent);

        if ($sent >= $this->minimum) {
            if ($run->sinceResume(SendRunCounter::Complained) * 100 > $this->complaintRate * $sent) {
                return PauseReason::Complaints;
            }

            if ($run->sinceResume(SendRunCounter::Bounced) * 100 > $this->bounceRate * $sent) {
                return PauseReason::Bounces;
            }
        }

        $failed = $run->sinceResume(SendRunCounter::Failed);
        $attempts = $sent + $failed;

        if ($attempts >= $this->minimum && $failed * 100 > $this->transportFailureRate * $attempts) {
            return PauseReason::TransportFailures;
        }

        return null;
    }
}

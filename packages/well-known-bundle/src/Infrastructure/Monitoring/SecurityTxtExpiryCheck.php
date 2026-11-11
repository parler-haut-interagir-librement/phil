<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Monitoring;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Psr\Clock\ClockInterface;

/**
 * The `Expires` of the security.txt as a check of phil/monitoring-bundle: up while the date is far, degraded once it is
 * `$warningDays` days away or less, down once it has passed, since the file then tells reporters nothing they may trust.
 * The bundle registers it when security.txt is on and phil/monitoring-bundle is installed.
 */
final readonly class SecurityTxtExpiryCheck implements HealthCheck
{
    public const string NAME = 'security_txt';

    public const int DEFAULT_WARNING_DAYS = 30;

    private const int DAY = 86_400;

    /**
     * @throws InvalidValue when the warning is not a number of days, 1 or more
     */
    public function __construct(
        private DateTimeImmutable $expires,
        private ClockInterface $clock,
        private int $warningDays = self::DEFAULT_WARNING_DAYS,
    ) {
        if ($warningDays < 1) {
            throw InvalidValue::because(\sprintf('The warning before the security.txt expires is a number of days, 1 or more; %d is not.', $warningDays));
        }
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $left = $this->expires->getTimestamp() - $this->clock->now()->getTimestamp();
        $date = $this->expires->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);

        if ($left <= 0) {
            return CheckOutcome::down(\sprintf('The security.txt expired on %s.', $date));
        }

        // The days left, rounded up: a file that expires in an hour expires in 1 day.
        $days = intdiv($left + self::DAY - 1, self::DAY);

        if ($left <= $this->warningDays * self::DAY) {
            return CheckOutcome::degraded(\sprintf('The security.txt expires on %s, in %d %s.', $date, $days, 1 === $days ? 'day' : 'days'));
        }

        return CheckOutcome::up(\sprintf('Expires on %s.', $date));
    }
}

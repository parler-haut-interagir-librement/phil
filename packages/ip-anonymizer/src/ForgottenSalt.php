<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\ExpiringSaltStore;
use Phil\IpAnonymizer\Contract\SaltProvider;
use Phil\IpAnonymizer\Exception\InvalidOption;
use Phil\IpAnonymizer\Internal\SaltPeriod;
use Psr\Clock\ClockInterface;

/**
 * A random salt per period of the clock, a new one each UTC day by default, forgotten at the end of its period.
 *
 * The first digest of a period draws 32 random bytes and claims them in an {@see ExpiringSaltStore}, for the time the
 * period still lasts; every server then reads the same salt. Once the entry has expired **nobody can derive that salt
 * again**, not even the operator: the digests of one address cannot be linked from one period to the next. This is
 * the provider to reach for unless nothing may be stored ({@see RotatingSalt}).
 *
 * The identifier of a salt is `<period seconds>-<index>`, `86400-20400` for 2025-11-08 with daily periods. It holds
 * no state of its own: the salt is read from the store on every digest, so a worker keeps nothing past the period.
 */
final readonly class ForgottenSalt implements SaltProvider
{
    public const int DAY = 86_400;

    /**
     * @var positive-int
     */
    private int $periodSeconds;

    /**
     * @throws InvalidOption when the period is shorter than a second
     */
    public function __construct(
        private ExpiringSaltStore $store,
        private ClockInterface $clock,
        int $periodSeconds = self::DAY,
    ) {
        $this->periodSeconds = SaltPeriod::length($periodSeconds);
    }

    /**
     * @throws InvalidOption when the clock reads an instant before the Unix epoch
     */
    #[Override]
    public function current(): Salt
    {
        $period = SaltPeriod::at($this->clock->now(), $this->periodSeconds);

        return Salt::of(
            $period->saltId,
            $this->store->claim($period->saltId, random_bytes(Salt::RECOMMENDED_BYTES), $period->secondsLeft),
        );
    }
}

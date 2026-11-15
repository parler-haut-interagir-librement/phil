<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Handler;

use DateInterval;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;

/**
 * Reminds, for each required policy, the members who will not be in order some days from now — they have not accepted
 * the version required then, a version taking effect by then, or their acceptance lapses by then — of the version to
 * accept, 500 members at a time.
 */
final readonly class SendTermsRemindersHandler
{
    public function __construct(
        private Policies $policies,
        private VersionCatalog $catalog,
        private Outstanding $outstanding,
        private TermsNotifier $notifier,
        private ClockInterface $clock,
        private int $daysAhead = 14,
    ) {
    }

    public function __invoke(): void
    {
        $then = $this->clock->now()->add(new DateInterval('P'.$this->daysAhead.'D'));
        foreach ($this->policies->required() as $policy) {
            $version = $this->catalog->inForce($policy->key, $then);

            if (!$version instanceof PolicyVersion) {
                continue;
            }

            $batch = [];

            foreach ($this->outstanding->at($policy->key, $then) as $member) {
                $batch[] = $member;

                if (Outstanding::BATCH === \count($batch)) {
                    $this->notifier->remind($policy->key, $version->number, $batch);
                    $batch = [];
                }
            }

            if ([] !== $batch) {
                $this->notifier->remind($policy->key, $version->number, $batch);
            }
        }
    }
}

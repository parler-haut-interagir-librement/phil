<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Subscription;

use Closure;
use Override;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\LimitProvider;
use Phil\UsageMeter\Meter;

/**
 * Limits written in the features of a subscriber's offers: `usage:<meter>:<limit>`, as in `usage:exports:20` or
 * `usage:projects:unlimited`. A subscriber holding several offers gets the most generous limit they name; one whose
 * features name none for a meter gets the meter's own limit — the free tier's.
 *
 * A feature after the prefix that is not a limit (`usage:exports:many`) is skipped: an offer's mistake never blocks a
 * subscriber's action.
 */
final readonly class FeatureLimits implements LimitProvider
{
    /**
     * @param Closure(string): iterable<string> $features the subject's features
     * @param string                            $prefix   what starts a feature that names a limit
     */
    public function __construct(
        private Closure $features,
        private string $prefix = 'usage:',
    ) {
    }

    /**
     * The features of `phil/commerce-subscription-bundle`: those of every offer the subscriber holds a subscription
     * granting access to.
     */
    public static function fromSubscriptions(FeatureAccess $access, string $prefix = 'usage:'): self
    {
        return new self($access->features(...), $prefix);
    }

    #[Override]
    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        $marker = $this->prefix.$meter->name.':';
        $best = null;

        foreach (($this->features)($subject) as $feature) {
            if (!str_starts_with($feature, $marker)) {
                continue;
            }

            $limit = Limit::parse(substr($feature, \strlen($marker)));

            if ($limit instanceof Limit) {
                $best = $best?->orHigher($limit) ?? $limit;
            }
        }

        return $best;
    }
}

<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Internal;

/**
 * The lower tail of a Poisson distribution: how likely it is to count `$observed` or fewer when `$mean` is expected.
 *
 * @internal
 */
final class PoissonTail
{
    /**
     * Above this count, the exact sum gives way to the Chernoff bound, so that the work stays bounded.
     */
    public const int EXACT_UP_TO = 10_000;

    /**
     * `P(X <= $observed)` for `X ~ Poisson($mean)`. At or above the mean, the answer is 1: a count that high is never
     * a drop. Over {@see self::EXACT_UP_TO}, the Chernoff bound `exp(-mean) (e mean / k)^k` stands in for the sum: it
     * is never below the exact tail, so it can only make an alert less likely.
     */
    public static function atMost(int $observed, float $mean): float
    {
        if ($observed < 0) {
            return 0.0;
        }

        if ($observed >= $mean) {
            return 1.0;
        }

        if ($observed > self::EXACT_UP_TO) {
            return exp(-$mean + $observed + $observed * log($mean / $observed));
        }

        // Each term from the previous one, in logarithms so that a large mean does not underflow to zero.
        $logMean = log($mean);
        $logTerm = -$mean;
        $largest = $logTerm;
        $logTerms = [$logTerm];

        for ($i = 1; $i <= $observed; ++$i) {
            $logTerm += $logMean - log($i);
            $logTerms[] = $logTerm;
            $largest = max($largest, $logTerm);
        }

        $sum = 0.0;

        foreach ($logTerms as $term) {
            $sum += exp($term - $largest);
        }

        return min(1.0, exp($largest + log($sum)));
    }
}

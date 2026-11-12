<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Collection;

use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\ValueObject\PrivacySignals;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;

/**
 * Decides what a page view does to the counters.
 *
 * **Where it stands in collection:** the origin is resolved to a declared site; the site's status is checked
 * (`SiteStatus::collects()`) **before** the rate limiter is consumed, so a page view of a suspended or retired
 * site neither spends a budget nor leaves a digest in the limiter's state; then the limiter; then this rule.
 * The rule checks the status again, first, so that no later step can turn a refusal into a write.
 *
 * The order is fixed, and each step only ever narrows: a site that does not collect refuses; a privacy signal,
 * a declared crawler or a spam referrer counts nothing; a robot verdict advances only the robot-verdict counter;
 * a page view without a usable verdict counts nothing at all; everything else is audience.
 *
 * The verdict is the one `phil/bot-detection-bundle` reads (`Verdict::fromSubmitted()`), strictly. A missing or
 * malformed verdict is `Unknown`, **not a robot**: a detector that fails in the browser must not turn every
 * person into a robot verdict, which would also hide the silence of the detector behind a wave of robots.
 */
final readonly class AdmissionRule
{
    public function decide(
        SiteStatus $status,
        PrivacySignals $signals,
        bool $declaredCrawler,
        ReferralKind $referral,
        Verdict $verdict,
    ): Admission {
        return match (true) {
            !$status->collects() => Admission::SiteNotCollecting,
            $signals->optOut() => Admission::OptedOut,
            $declaredCrawler => Admission::DeclaredCrawler,
            ReferralKind::Spam === $referral => Admission::ReferrerSpam,
            Verdict::Robot === $verdict => Admission::RobotVerdictOnly,
            Verdict::Unknown === $verdict => Admission::NoVerdict,
            default => Admission::CountAudience,
        };
    }
}

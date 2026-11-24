<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\Spf\Qualifier;
use Phil\MailAuthCheck\Spf\SpfAnalyser;
use Phil\MailAuthCheck\Spf\SpfAnalysis;
use Phil\MailAuthCheck\Spf\SpfState;

/**
 * Turns the analysis of an SPF record into findings, for the SPF check of the sending domain and for the check of the
 * return path.
 *
 * @internal
 */
final class SpfFindings
{
    /**
     * Past this many lookups, a warning says one more provider may break the record.
     */
    public const int NEAR_LIMIT = 8;

    /**
     * @param list<string> $expectedIncludes
     *
     * @return non-empty-list<Finding>
     */
    public static function of(string $check, SpfAnalysis $analysis, array $expectedIncludes): array
    {
        $domain = $analysis->domain;

        return match ($analysis->state) {
            SpfState::LookupFailed => [Finding::fail($check, $check.'.lookup-failed', \sprintf('The SPF record of %s could not be read: the DNS lookup failed.', $domain))],
            SpfState::Missing => [Finding::fail($check, $check.'.missing', \sprintf('%s publishes no SPF record (a TXT record starting with "v=spf1").', $domain))],
            SpfState::Multiple => [Finding::fail($check, $check.'.multiple', \sprintf('%s publishes more than one SPF record, which receivers treat as a permanent error: merge them into one.', $domain))],
            SpfState::Invalid => [Finding::fail($check, $check.'.invalid', \sprintf('The SPF record of %s cannot be read: %s', $domain, (string) $analysis->reason))],
            SpfState::Found => self::found($check, $analysis, $expectedIncludes),
        };
    }

    /**
     * @param list<string> $expectedIncludes
     *
     * @return non-empty-list<Finding>
     */
    private static function found(string $check, SpfAnalysis $analysis, array $expectedIncludes): array
    {
        $domain = $analysis->domain;
        $findings = [self::lookups($check, $analysis)];

        foreach ($analysis->loops as $loop) {
            $findings[] = Finding::fail($check, $check.'.include-loop', \sprintf('The SPF record of %s includes %s again inside itself, a loop receivers treat as a permanent error.', $domain, $loop));
        }

        foreach ($analysis->broken as $include => $reason) {
            $findings[] = Finding::fail($check, $check.'.include-broken', \sprintf('The SPF record of %s includes %s, but %s.', $domain, $include, $reason));
        }

        foreach ($analysis->macros as $macro) {
            $findings[] = Finding::warning($check, $check.'.macro', \sprintf('The SPF record of %s names %s, a macro that depends on each message: it is counted, not followed.', $domain, $macro));
        }

        foreach ($expectedIncludes as $include) {
            $findings[] = \in_array($include, $analysis->includes, true)
                ? Finding::pass($check, $check.'.include', \sprintf('The SPF record of %s includes %s.', $domain, $include))
                : Finding::fail($check, $check.'.include-missing', \sprintf('The SPF record of %s does not include %s: add "include:%s".', $domain, $include, $include));
        }

        $all = self::all($check, $analysis);

        if ($all instanceof Finding) {
            $findings[] = $all;
        }

        return $findings;
    }

    private static function lookups(string $check, SpfAnalysis $analysis): Finding
    {
        $domain = $analysis->domain;

        if ($analysis->exceedsLookupLimit()) {
            return Finding::fail($check, $check.'.too-many-lookups', \sprintf(
                'The SPF record of %s needs more than %d DNS lookups (include, a, mx, ptr, exists, redirect), which receivers treat as a permanent error.',
                $domain,
                SpfAnalyser::LOOKUP_LIMIT,
            ));
        }

        $message = \sprintf('The SPF record of %s needs %d of the %d DNS lookups allowed.', $domain, $analysis->lookups, SpfAnalyser::LOOKUP_LIMIT);

        return $analysis->lookups > self::NEAR_LIMIT
            ? Finding::warning($check, $check.'.lookups-near-limit', $message.' One more provider may break it.')
            : Finding::pass($check, $check.'.lookups', $message);
    }

    private static function all(string $check, SpfAnalysis $analysis): ?Finding
    {
        $domain = $analysis->domain;

        return match ($analysis->all) {
            Qualifier::Pass => Finding::fail($check, $check.'.all-pass', \sprintf('The SPF record of %s ends with "+all", which lets any server send as it.', $domain)),
            Qualifier::Fail => Finding::pass($check, $check.'.all-fail', \sprintf('The SPF record of %s ends with "-all": mail from other servers fails SPF.', $domain)),
            Qualifier::SoftFail => Finding::pass($check, $check.'.all-softfail', \sprintf('The SPF record of %s ends with "~all": mail from other servers soft-fails SPF, and DMARC decides.', $domain)),
            Qualifier::Neutral => Finding::warning($check, $check.'.all-neutral', \sprintf('The SPF record of %s ends with "?all", which says nothing of other servers: prefer "~all" or "-all".', $domain)),
            null => $analysis->exceedsLookupLimit()
                ? null
                : Finding::warning($check, $check.'.no-all', \sprintf('The SPF record of %s has no "all", so other servers get a neutral result: end it with "~all" or "-all".', $domain)),
        };
    }
}

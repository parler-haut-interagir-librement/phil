<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Check;

use Override;
use Phil\MailAuthCheck\Alignment;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dmarc\DmarcRecord;
use Phil\MailAuthCheck\Dmarc\DmarcTreeWalk;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\SenderProfile;

/**
 * The DMARC record that applies to the sending domain (RFC 9989): its own, or a parent's found by the DNS tree walk; a
 * policy at least as strict as the profile expects (`none` passes with a warning when the profile accepts it); and the
 * alignment of the return path with the sending domain under the record's SPF alignment.
 */
final readonly class DmarcCheck implements Check
{
    public const string NAME = 'dmarc';

    public function __construct(
        private DmarcTreeWalk $walk = new DmarcTreeWalk(),
    ) {
    }

    #[Override]
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        $domain = $profile->sendingDomain;
        $discovery = $this->walk->policy($domain, $dns);

        if ($discovery->lookupFailed) {
            return [$this->lookupFailed($domain)];
        }

        $findings = [];

        foreach ($discovery->ignored as $name => $reason) {
            $findings[] = $name === $domain
                ? Finding::fail(self::NAME, 'dmarc.invalid', \sprintf('The DMARC record at _dmarc.%s is skipped: %s', $name, $reason))
                : Finding::warning(self::NAME, 'dmarc.parent-invalid', \sprintf('The DMARC record at _dmarc.%s is skipped: %s', $name, $reason));
        }

        $record = $discovery->record;

        if (!$record instanceof DmarcRecord || null === $discovery->foundAt) {
            $findings[] = Finding::fail(self::NAME, 'dmarc.missing', \sprintf('No DMARC record applies to %s: publish one at _dmarc.%s, at least "v=DMARC1; p=none".', $domain, $domain));

            return $findings;
        }

        if ($discovery->isInherited()) {
            $findings[] = Finding::pass(self::NAME, 'dmarc.inherited', \sprintf('%s has no DMARC record of its own: the one of %s applies.', $domain, $discovery->foundAt));
        }

        array_push($findings, ...$this->policy($profile, $record, $discovery->foundAt, $discovery->isInherited()));

        try {
            $findings[] = $this->returnPath($profile, $record, $dns);
        } catch (DnsLookupFailed) {
            $findings[] = $this->lookupFailed($domain);
        }

        $findings[] = Alignment::Strict === $record->dkimAlignment
            ? Finding::pass(self::NAME, 'dmarc.dkim-alignment', \sprintf('DKIM aligns when a signature uses d=%s exactly (strict).', $domain))
            : Finding::pass(self::NAME, 'dmarc.dkim-alignment', \sprintf('DKIM aligns when a signature uses d=%s or another domain of its organisational domain (relaxed).', $domain));

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function policy(SenderProfile $profile, DmarcRecord $record, string $foundAt, bool $inherited): array
    {
        $findings = [];
        $policy = $record->policyFor($inherited);

        if (!$record->policy instanceof DmarcPolicy) {
            $findings[] = Finding::warning(self::NAME, 'dmarc.no-policy', \sprintf('The DMARC record of %s has no "p" tag: receivers read it as "p=none".', $foundAt));
        }

        if (!$policy->isAtLeast($profile->minimumPolicy)) {
            $findings[] = Finding::fail(self::NAME, 'dmarc.policy-too-weak', \sprintf(
                'The DMARC policy for %s is "%s"; this sender expects at least "%s".',
                $profile->sendingDomain,
                $policy->value,
                $profile->minimumPolicy->value,
            ));
        } elseif (DmarcPolicy::None === $policy) {
            $findings[] = Finding::warning(self::NAME, 'dmarc.policy-none', \sprintf('The DMARC policy for %s is "none": it only monitors, and mail spoofing the domain is still delivered.', $profile->sendingDomain));
        } else {
            $findings[] = Finding::pass(self::NAME, 'dmarc.policy', \sprintf('The DMARC policy for %s is "%s".', $profile->sendingDomain, $policy->value));
        }

        if ($record->testing) {
            $findings[] = Finding::warning(self::NAME, 'dmarc.testing', \sprintf('The DMARC record of %s says it is being tested ("t=y"): receivers apply its policy one step weaker.', $foundAt));
        }

        if ([] === $record->aggregateReports) {
            $findings[] = Finding::warning(self::NAME, 'dmarc.no-reports', \sprintf('The DMARC record of %s asks for no aggregate report ("rua"): nobody sees who sends as the domain.', $foundAt));
        }

        return $findings;
    }

    /**
     * @throws DnsLookupFailed
     */
    private function returnPath(SenderProfile $profile, DmarcRecord $record, TxtResolver $dns): Finding
    {
        $returnPath = $profile->returnPathDomain;
        $domain = $profile->sendingDomain;
        $mode = Alignment::Strict === $record->spfAlignment ? 'strict' : 'relaxed';

        $aligned = $returnPath === $domain || (Alignment::Relaxed === $record->spfAlignment
            && $this->walk->organisationalDomain($returnPath, $dns) === $this->walk->organisationalDomain($domain, $dns));

        if ($aligned) {
            return Finding::pass(self::NAME, 'dmarc.return-path-aligned', \sprintf('The return path %s aligns with %s under %s SPF alignment.', $returnPath, $domain, $mode));
        }

        $message = \sprintf('The return path %s does not align with %s under %s SPF alignment: DMARC rests on DKIM alone.', $returnPath, $domain, $mode);

        return [] === $profile->dkimSelectors
            ? Finding::fail(self::NAME, 'dmarc.return-path-unaligned', $message.' No DKIM selector is expected, so DMARC fails.')
            : Finding::warning(self::NAME, 'dmarc.return-path-unaligned', $message);
    }

    private function lookupFailed(string $domain): Finding
    {
        return Finding::fail(self::NAME, 'dmarc.lookup-failed', \sprintf('The DMARC record of %s could not be read: a DNS lookup failed.', $domain));
    }
}

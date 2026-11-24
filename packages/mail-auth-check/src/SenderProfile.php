<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

use Phil\MailAuthCheck\Exception\InvalidDomain;
use Phil\MailAuthCheck\Exception\InvalidProfile;
use Phil\MailAuthCheck\Internal\DomainName;

/**
 * What a sender expects of its DNS: the domain of its `From` address, the domain of its return path (the envelope
 * sender bounces go to), the DKIM selectors its providers sign with, the SPF includes of those providers, and the
 * weakest DMARC policy it accepts.
 *
 * The names are kept in lowercase ASCII, an internationalised name converted by ext-intl; duplicates are dropped.
 */
final readonly class SenderProfile
{
    public const int MAX_SELECTORS = 16;

    public const int MAX_INCLUDES = 16;

    public string $sendingDomain;

    public string $returnPathDomain;

    /** @var list<string> */
    public array $dkimSelectors;

    /** @var list<string> */
    public array $spfIncludes;

    /**
     * @param ?string      $returnPathDomain the sending domain when null
     * @param list<string> $dkimSelectors    the selectors, without `._domainkey`, such as `s1` or `mail.2026`
     * @param list<string> $spfIncludes      the domains the SPF records should include, such as a provider's
     *                                       `_spf.provider.example`
     *
     * @throws InvalidDomain  when a name is not a valid host name
     * @throws InvalidProfile when more than 16 selectors or includes are given
     */
    public function __construct(
        string $sendingDomain,
        ?string $returnPathDomain = null,
        array $dkimSelectors = [],
        array $spfIncludes = [],
        public DmarcPolicy $minimumPolicy = DmarcPolicy::None,
    ) {
        if (\count($dkimSelectors) > self::MAX_SELECTORS) {
            throw InvalidProfile::tooMany('DKIM selectors', self::MAX_SELECTORS);
        }

        if (\count($spfIncludes) > self::MAX_INCLUDES) {
            throw InvalidProfile::tooMany('SPF includes', self::MAX_INCLUDES);
        }

        $this->sendingDomain = DomainName::toAscii($sendingDomain);
        $this->returnPathDomain = null === $returnPathDomain ? $this->sendingDomain : DomainName::toAscii($returnPathDomain);
        $this->dkimSelectors = array_values(array_unique(array_map(DomainName::selector(...), $dkimSelectors)));
        $this->spfIncludes = array_values(array_unique(array_map(
            static fn (string $include): string => DomainName::dnsName(trim($include))
                ?? throw InvalidDomain::because($include, 'it is not a DNS name of letters, digits, hyphens and underscores'),
            $spfIncludes,
        )));
    }
}

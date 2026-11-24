<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Check;

use Override;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Internal\SpfFindings;
use Phil\MailAuthCheck\SenderProfile;
use Phil\MailAuthCheck\Spf\SpfAnalyser;

/**
 * The SPF record of the sending domain (RFC 7208): exactly one, readable, within ten DNS lookups, without loops,
 * including the expected providers, and not ending with `+all`.
 */
final readonly class SpfCheck implements Check
{
    public const string NAME = 'spf';

    public function __construct(
        private SpfAnalyser $analyser = new SpfAnalyser(),
    ) {
    }

    #[Override]
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        return SpfFindings::of(self::NAME, $this->analyser->analyse($profile->sendingDomain, $dns), $profile->spfIncludes);
    }
}

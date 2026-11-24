<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Check;

use Override;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\Internal\SpfFindings;
use Phil\MailAuthCheck\SenderProfile;
use Phil\MailAuthCheck\Spf\SpfAnalyser;

/**
 * The SPF record of the return path, the domain receivers check SPF against: when it differs from the sending domain,
 * the same checks as `SpfCheck`, the expected providers included.
 */
final readonly class ReturnPathCheck implements Check
{
    public const string NAME = 'return-path';

    public function __construct(
        private SpfAnalyser $analyser = new SpfAnalyser(),
    ) {
    }

    #[Override]
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        if ($profile->returnPathDomain === $profile->sendingDomain) {
            return [Finding::pass(self::NAME, self::NAME.'.same-domain', \sprintf(
                'The return path is the sending domain %s: its SPF record is the one the SPF check reads.',
                $profile->sendingDomain,
            ))];
        }

        return SpfFindings::of(self::NAME, $this->analyser->analyse($profile->returnPathDomain, $dns), $profile->spfIncludes);
    }
}

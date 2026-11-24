<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

use Phil\MailAuthCheck\Check\DkimCheck;
use Phil\MailAuthCheck\Check\DmarcCheck;
use Phil\MailAuthCheck\Check\ReturnPathCheck;
use Phil\MailAuthCheck\Check\SpfCheck;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dns\DnsGetRecordResolver;
use Phil\MailAuthCheck\Internal\MemoizedResolver;

/**
 * Checks the DNS of a sender profile: SPF, DKIM, DMARC and the return path by default, in that order, then the checks
 * an application adds. Each name is asked once per call; nothing is kept between calls.
 */
final readonly class MailAuthCheck
{
    /** @var list<Check> */
    private array $checks;

    /**
     * @param ?iterable<Check> $checks the checks to run, in order; the four of the package when null
     */
    public function __construct(
        private TxtResolver $resolver = new DnsGetRecordResolver(),
        ?iterable $checks = null,
    ) {
        $this->checks = null === $checks ? self::defaultChecks() : array_values([...$checks]);
    }

    /**
     * @return list<Check>
     */
    public static function defaultChecks(): array
    {
        return [new SpfCheck(), new DkimCheck(), new DmarcCheck(), new ReturnPathCheck()];
    }

    public function check(SenderProfile $profile): Report
    {
        $dns = new MemoizedResolver($this->resolver);
        $findings = [];

        foreach ($this->checks as $check) {
            array_push($findings, ...$check->run($profile, $dns));
        }

        return new Report($profile, $findings);
    }
}

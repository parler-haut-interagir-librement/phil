<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Bounces;

use Phil\BounceParser\BounceParser;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\NoticeSource;

/**
 * Reads a raw message of the bounce mailbox — a delivery status notification, an abuse report, a bounce written as
 * text — through the bounce parser, and has the rules decide each notice it gives. The report is named by a hash of
 * the raw message: the same message read twice is decided once.
 */
final readonly class BounceIngestor
{
    public function __construct(
        private BounceParser $parser,
        private BounceNotices $notices,
        private BounceRules $rules,
    ) {
    }

    /**
     * @return list<BounceDecision> the decision of each notice, in the order of the report's addresses
     */
    public function ingest(string $raw, NoticeSource $source = NoticeSource::Message): array
    {
        $report = $this->parser->parse($raw);

        return array_map($this->rules->apply(...), $this->notices->fromReport($report, 'raw:'.hash('sha256', $raw), $source));
    }
}

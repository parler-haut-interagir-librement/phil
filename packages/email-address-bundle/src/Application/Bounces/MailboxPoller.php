<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Bounces;

use Phil\EmailAddressBundle\Application\Contract\BounceMailbox;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\NoticeSource;

/**
 * Reads the bounce mailbox: each message not handled yet is ingested, then acknowledged. A message whose ingestion
 * throws is not acknowledged, and is fetched again by the next poll.
 */
final readonly class MailboxPoller
{
    public function __construct(
        private BounceMailbox $mailbox,
        private BounceIngestor $ingestor,
    ) {
    }

    /**
     * @param positive-int $limit the most messages to read
     *
     * @return array<string, list<BounceDecision>> the decisions of each message, by its identifier in the mailbox
     */
    public function poll(int $limit = 50): array
    {
        $decisions = [];

        foreach ($this->mailbox->fetch($limit) as $message) {
            $decisions[$message->id] = $this->ingestor->ingest($message->raw, NoticeSource::Mailbox);
            $this->mailbox->acknowledge($message);
        }

        return $decisions;
    }
}

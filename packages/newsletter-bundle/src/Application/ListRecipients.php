<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\NewsletterBundle\Application\Contract\SegmentResolver;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Model\Campaign;

/**
 * The bundle's own recipients: every subscriber of the campaign's list who may receive, read in pages of 500. A
 * campaign that names a segment needs a resolver that knows segments, and is refused here.
 */
final readonly class ListRecipients implements SegmentResolver
{
    public const int PAGE = 500;

    public function __construct(
        private SubscriberRepository $subscribers,
    ) {
    }

    #[Override]
    public function recipients(Campaign $campaign, DateTimeImmutable $now): iterable
    {
        if (null !== $campaign->segment) {
            throw new LogicException(\sprintf('The campaign names the segment "%s", and no segment resolver is configured.', $campaign->segment));
        }

        $after = null;

        do {
            $page = $this->subscribers->recipients($campaign->listId, $now, $after, self::PAGE);

            foreach ($page as $id) {
                yield $id;
                $after = $id;
            }
        } while (self::PAGE === \count($page));
    }
}

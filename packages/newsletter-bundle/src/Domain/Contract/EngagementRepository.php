<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * Where clicks and the reasons people leave are counted. A click is a total per link unless the campaign chose to
 * count clicks per recipient; a reason is a total per day, without anything that names who gave it.
 */
interface EngagementRepository
{
    public function countClick(string $campaignId, int $link): void;

    /**
     * @return array<int, int> the clicks of each link of a campaign, by link index
     */
    public function clicks(string $campaignId): array;

    public function recordRecipientClick(string $campaignId, int $link, string $subscriberId, DateTimeImmutable $at): void;

    /**
     * @return list<array{campaign: string, link: int, at: DateTimeImmutable}>
     */
    public function recipientClicks(string $subscriberId): array;

    /**
     * @return int how many were forgotten
     */
    public function forgetRecipientClicks(string $subscriberId): int;

    public function countUnsubscribeReason(Site $site, ?string $listId, UnsubscribeReason $reason, DateTimeImmutable $day): void;

    /**
     * @return array<value-of<UnsubscribeReason>, int> how often each reason was given on the site since a day
     */
    public function unsubscribeReasons(Site $site, DateTimeImmutable $since): array;
}

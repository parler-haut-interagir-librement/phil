<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Model\Campaign;

/**
 * Where the campaigns, and the links their messages track, are kept.
 */
interface CampaignRepository
{
    public function save(Campaign $campaign): void;

    public function get(string $id): ?Campaign;

    /**
     * The scheduled campaigns whose time has come.
     *
     * @return list<Campaign>
     */
    public function due(DateTimeImmutable $now): array;

    /**
     * The index of a link of the campaign, kept the first time it is asked for, so that a click is redirected only to
     * an address the campaign holds.
     */
    public function linkIndex(string $campaignId, string $url): int;

    /**
     * The address of a link of the campaign; `null` when the campaign has no link of that index.
     */
    public function link(string $campaignId, int $index): ?string;
}

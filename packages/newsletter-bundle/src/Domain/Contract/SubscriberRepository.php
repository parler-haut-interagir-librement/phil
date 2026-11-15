<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * Where the subscribers and their memberships are kept.
 */
interface SubscriberRepository
{
    public function save(Subscriber $subscriber): void;

    public function get(string $id): ?Subscriber;

    /**
     * The site's subscriber of an address, by `Subscriber::keyOf()`.
     */
    public function byEmail(Site $site, string $emailKey): ?Subscriber;

    public function byExternalId(Site $site, string $externalId): ?Subscriber;

    /**
     * The subscribers the application knows by an external id, on every site.
     *
     * @return list<Subscriber>
     */
    public function withExternalId(string $externalId): array;

    /**
     * Forgets a subscriber and their memberships.
     */
    public function remove(string $id): void;

    public function saveMembership(Membership $membership): void;

    public function membership(string $subscriberId, string $listId): ?Membership;

    /**
     * @return list<Membership>
     */
    public function memberships(string $subscriberId): array;

    /**
     * The ids of the subscribers of a list who may receive now — active on the list, and active or at the end of a
     * pause —, after an id, in order, so that a large list is read in pages.
     *
     * @return list<string>
     */
    public function recipients(string $listId, DateTimeImmutable $now, ?string $after, int $limit): array;
}

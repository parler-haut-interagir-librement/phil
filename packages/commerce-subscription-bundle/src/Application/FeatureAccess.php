<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Application\Contract\OrganizationMembership;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Psr\Clock\ClockInterface;

/**
 * What a subscriber may use: the features of every offer they hold a subscription granting access to — trialing,
 * active, or past due while its retries last — and of every such subscription held by an organization they belong
 * to, as `OrganizationMembership` tells; an organization's offer that counts seats opens its features to the members
 * holding one of them. A subscription past due gives what the offer's dunning stages keep at that many days past due
 * — every feature, some, or none —, every feature for an offer without stages.
 *
 * A subscriber's features are read once and kept until `forget()` or `reset()`: a page that checks five features
 * reads the subscriptions once. `Subscriptions`, `Gifts` and `Seats` forget a subscriber whose subscription or seat
 * they change, and forgetting an organization forgets the members read through it; the application forgets a member
 * who joins or leaves an organization. The bundle tags the service `kernel.reset`, so that a long-running worker
 * reads them again for each request or message.
 */
final class FeatureAccess
{
    /** How many organizations of one member are read. */
    private const int ORGANIZATIONS = 100;

    /** @var array<string, list<string>> subscriber → features */
    private array $known = [];

    /** @var array<string, array<string, string>> organization → the members whose features were read through it */
    private array $members = [];

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly OfferRepository $offers,
        private readonly ?OrganizationMembership $organizations = null,
        private readonly ?SeatRepository $seats = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @return list<string> the features, each once, in the order the offers list them, the subscriber's own first
     */
    public function features(string $subscriber): array
    {
        return $this->known[$subscriber] ??= $this->read($subscriber);
    }

    public function grants(string $subscriber, string $feature): bool
    {
        return \in_array($feature, $this->features($subscriber), true);
    }

    /**
     * Reads the subscriber's features again on the next check, and, for an organization, those of the members read
     * through it.
     */
    public function forget(string $subscriber): void
    {
        unset($this->known[$subscriber]);

        foreach ($this->members[$subscriber] ?? [] as $member) {
            unset($this->known[$member]);
        }

        unset($this->members[$subscriber]);
    }

    /**
     * Reads every subscriber's features again on the next check.
     */
    public function reset(): void
    {
        $this->known = [];
        $this->members = [];
    }

    /**
     * @return list<string>
     */
    private function read(string $subscriber): array
    {
        $subscribers = [$subscriber];

        foreach (\array_slice($this->organizations?->organizationsOf($subscriber) ?? [], 0, self::ORGANIZATIONS) as $organization) {
            if (!\in_array($organization, $subscribers, true)) {
                $subscribers[] = $organization;
                $this->members[$organization][$subscriber] = $subscriber;
            }
        }

        // Every subscription in one read, whatever the number of organizations; the subscriber's own come first.
        $own = [];
        $shared = [];

        foreach ($this->subscriptions->forSubscribers($subscribers) as $subscription) {
            if ($subscription->grantsAccess()) {
                if ($subscription->subscriber === $subscriber) {
                    $own[] = $subscription;
                } else {
                    $shared[] = $subscription;
                }
            }
        }

        $granting = [...$own, ...$shared];
        // The offers in one read, whatever the number of subscriptions.
        $offers = $this->offers->findByCodes(array_map(static fn (Subscription $subscription): string => $subscription->offerCode(), $granting));
        $features = [];
        $now = $this->clock?->now() ?? new DateTimeImmutable();

        foreach ($granting as $subscription) {
            $offer = $offers[$subscription->offerCode()] ?? null;

            if (null === $offer || ($subscription->subscriber !== $subscriber && null !== $offer->seats && true !== $this->seats?->holdsSeat($subscription->id, $subscriber, $offer->seats))) {
                continue;
            }

            $days = $subscription->daysPastDue($now);

            foreach (null === $days ? $offer->features() : $offer->featuresPastDue($days) as $feature) {
                $features[$feature] = $feature;
            }
        }

        return array_values($features);
    }
}

<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\EmailValidator;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Exception\SubscriptionRefused;
use Phil\NewsletterBundle\Application\Message\SendConfirmation;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\SuppressionRepository;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Subscribing, confirming, leaving, pausing and forgetting: every change of a subscriber, each saved, then announced
 * with an `AudienceChanged` event.
 *
 * An address is validated by `phil/email-address` before anything is kept. An address that bounced or complained is
 * never subscribed again and is sent nothing. A list with a double opt-in keeps the subscription pending until the
 * link of the confirmation is followed; the confirmation goes out through Messenger.
 */
final readonly class Subscriptions
{
    public function __construct(
        private MailingListRepository $lists,
        private SubscriberRepository $subscribers,
        private SuppressionRepository $suppressions,
        private DeliveryRepository $deliveries,
        private EngagementRepository $engagement,
        private EmailValidator $validator,
        private MessageBusInterface $bus,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws SubscriptionRefused when the address is refused
     */
    public function subscribe(MailingList $list, string $email, ?string $externalId = null): SubscribeOutcome
    {
        $address = $this->validated($email);
        $now = $this->clock->now();
        $key = Subscriber::keyOf($address);

        if ($this->suppressions->isSuppressed($list->site, $key)) {
            return SubscribeOutcome::Suppressed;
        }

        $subscriber = $this->subscribers->byEmail($list->site, $key);

        if ($subscriber instanceof Subscriber && $subscriber->isSuppressed()) {
            return SubscribeOutcome::Suppressed;
        }

        $created = !$subscriber instanceof Subscriber;
        $subscriber ??= new Subscriber(Uuid::v7()->toRfc4122(), $list->site, $address, $externalId, SubscriberStatus::Pending, $now);

        if (null !== $externalId && null === $subscriber->externalId) {
            $subscriber->linkExternalId($externalId);
        }

        $membership = $this->subscribers->membership($subscriber->id, $list->id);

        if ($membership instanceof Membership && $membership->isActive() && SubscriberStatus::Unsubscribed !== $subscriber->status) {
            return SubscribeOutcome::AlreadySubscribed;
        }

        $membership ??= new Membership($subscriber->id, $list->id, MembershipStatus::Pending, $now);

        if ($list->doubleOptIn && SubscriberStatus::Active !== $subscriber->status && SubscriberStatus::Paused !== $subscriber->status) {
            $subscriber->awaitConfirmation();
            $this->subscribers->save($subscriber);
            $this->subscribers->saveMembership(new Membership($subscriber->id, $list->id, MembershipStatus::Pending, $now));
            $this->bus->dispatch(new SendConfirmation($subscriber->id, $list->id));
            $this->announce(AudienceEventType::Subscribed, $subscriber, $list->id, $now);

            return SubscribeOutcome::ConfirmationSent;
        }

        if (SubscriberStatus::Paused !== $subscriber->status) {
            $subscriber->activate($now);
        }

        $membership->activate($now);
        $this->subscribers->save($subscriber);
        $this->subscribers->saveMembership($membership);

        if ($created) {
            $this->announce(AudienceEventType::Subscribed, $subscriber, $list->id, $now);
        }

        $this->announce(AudienceEventType::Confirmed, $subscriber, $list->id, $now);

        return SubscribeOutcome::Subscribed;
    }

    /**
     * The confirmation of a double opt-in, from its signed link.
     *
     * @return bool `false` when it was confirmed already, or the subscriber left meanwhile
     */
    public function confirm(string $subscriberId, string $listId): bool
    {
        $subscriber = $this->subscriber($subscriberId);
        $membership = $this->subscribers->membership($subscriberId, $listId);

        if (!$membership instanceof Membership || MembershipStatus::Pending !== $membership->status || $subscriber->isSuppressed()) {
            return false;
        }

        $now = $this->clock->now();

        if (SubscriberStatus::Paused !== $subscriber->status) {
            $subscriber->activate($now);
        }

        $membership->activate($now);
        $this->subscribers->save($subscriber);
        $this->subscribers->saveMembership($membership);
        $this->announce(AudienceEventType::Confirmed, $subscriber, $listId, $now);

        return true;
    }

    /**
     * Adds a subscriber who is already confirmed to another list, from the preference center or an automation.
     *
     * @return bool `false` when they were on it already, or may not receive
     */
    public function join(string $subscriberId, MailingList $list): bool
    {
        $subscriber = $this->subscriber($subscriberId);

        if (!\in_array($subscriber->status, [SubscriberStatus::Active, SubscriberStatus::Paused], true) || !$subscriber->site->equals($list->site)) {
            return false;
        }

        $now = $this->clock->now();
        $membership = $this->subscribers->membership($subscriberId, $list->id) ?? new Membership($subscriberId, $list->id, MembershipStatus::Pending, $now);

        if ($membership->isActive()) {
            return false;
        }

        $membership->activate($now);
        $this->subscribers->saveMembership($membership);
        $this->announce(AudienceEventType::Confirmed, $subscriber, $list->id, $now);

        return true;
    }

    /**
     * Leaves one list, or every list with `null`. A subscriber left with no list is unsubscribed. The reason, when one
     * is given, is counted for the site without anything that names who gave it.
     *
     * @return bool `false` when there was nothing to leave
     */
    public function leave(string $subscriberId, ?string $listId = null, ?UnsubscribeReason $reason = null): bool
    {
        $subscriber = $this->subscriber($subscriberId);
        $now = $this->clock->now();
        $left = false;

        foreach ($this->subscribers->memberships($subscriberId) as $membership) {
            if (MembershipStatus::Unsubscribed !== $membership->status && (null === $listId || $membership->listId === $listId)) {
                $membership->leave($now);
                $this->subscribers->saveMembership($membership);
                $left = true;
            }
        }

        $remaining = array_filter($this->subscribers->memberships($subscriberId), static fn (Membership $membership): bool => MembershipStatus::Unsubscribed !== $membership->status);

        if ([] === $remaining && SubscriberStatus::Unsubscribed !== $subscriber->status && !$subscriber->isSuppressed()) {
            $subscriber->unsubscribe();
            $this->subscribers->save($subscriber);
            $left = true;
        }

        if (!$left) {
            return false;
        }

        if ($reason instanceof UnsubscribeReason) {
            $this->engagement->countUnsubscribeReason($subscriber->site, $listId, $reason, $now);
        }

        $this->announce(AudienceEventType::Unsubscribed, $subscriber, $listId, $now);

        return true;
    }

    public function pause(string $subscriberId, DateTimeImmutable $until): void
    {
        $subscriber = $this->subscriber($subscriberId);
        $now = $this->clock->now();
        $subscriber->pause($until, $now);
        $this->subscribers->save($subscriber);
        $this->announce(AudienceEventType::Paused, $subscriber, null, $now);
    }

    public function resume(string $subscriberId): void
    {
        $subscriber = $this->subscriber($subscriberId);

        if (SubscriberStatus::Paused !== $subscriber->status) {
            return;
        }

        $subscriber->resume();
        $this->subscribers->save($subscriber);
        $this->announce(AudienceEventType::Resumed, $subscriber, null, $this->clock->now());
    }

    /**
     * Forgets a subscriber: their record, memberships, deliveries and clicks counted per recipient. An address that
     * bounced or complained stays suppressed, as a keyed hash.
     */
    public function forget(Subscriber $subscriber): void
    {
        if ($subscriber->isSuppressed()) {
            $this->suppressions->suppress($subscriber->site, $subscriber->emailKey(), $subscriber->status->value);
        }

        $this->engagement->forgetRecipientClicks($subscriber->id);
        $this->deliveries->removeOfSubscriber($subscriber->id);
        $this->subscribers->remove($subscriber->id);
        $this->announce(AudienceEventType::Erased, $subscriber, null, $this->clock->now());
    }

    public function list(string $listId): MailingList
    {
        return $this->lists->get($listId) ?? throw NotFound::of('list', $listId);
    }

    public function subscriber(string $subscriberId): Subscriber
    {
        return $this->subscribers->get($subscriberId) ?? throw NotFound::of('subscriber', $subscriberId);
    }

    /**
     * Announces a change once it is saved.
     */
    public function announce(AudienceEventType $type, Subscriber $subscriber, ?string $listId, DateTimeImmutable $now): void
    {
        $this->events->dispatch(new AudienceChanged($type, $subscriber->id, $subscriber->site->key, $listId, $subscriber->externalId, $now));
    }

    private function validated(string $email): string
    {
        $validation = $this->validator->validate($email);

        if (!$validation->isValid() || !$validation->address instanceof EmailAddress) {
            throw new SubscriptionRefused($validation->problems, $validation->suggestion?->toString());
        }

        return $validation->address->localPart.'@'.mb_strtolower($validation->address->domain);
    }
}

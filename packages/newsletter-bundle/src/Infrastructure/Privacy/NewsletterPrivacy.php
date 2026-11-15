<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The newsletter's part of an access or erasure request of `phil/privacy-bundle`, the data subject being the external
 * id the application gave its subscribers, on every site. The export lists each subscription — address, state,
 * lists —, the messages sent and the clicks counted per recipient; the erasure deletes all of them. An address that
 * bounced or complained stays suppressed as a keyed hash, which names nobody. Running it again finds nothing more and
 * reports the same.
 */
final readonly class NewsletterPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'newsletter';

    public function __construct(
        private SubscriberRepository $subscribers,
        private MailingListRepository $lists,
        private DeliveryRepository $deliveries,
        private EngagementRepository $engagement,
        private Subscriptions $subscriptions,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $subscriptions = array_map($this->exported(...), $this->subscribers->withExternalId($subject->value));

        return [] === $subscriptions ? [] : ['subscriptions' => $subscriptions];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        foreach ($this->subscribers->withExternalId($subject->value) as $subscriber) {
            $this->subscriptions->forget($subscriber);
        }

        return ErasureReport::of(self::MODULE, [
            'subscribers' => ErasureEffect::Deleted,
            'memberships' => ErasureEffect::Deleted,
            'deliveries' => ErasureEffect::Deleted,
            'recipient clicks' => ErasureEffect::Deleted,
            'suppressions' => ErasureEffect::Pseudonymised,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(Subscriber $subscriber): array
    {
        return [
            'site' => $subscriber->site->key,
            'email' => $subscriber->email,
            'status' => $subscriber->status->value,
            'subscribed_at' => $subscriber->createdAt->format(DateTimeInterface::ATOM),
            'confirmed_at' => $subscriber->confirmedAt?->format(DateTimeInterface::ATOM),
            'paused_until' => $subscriber->pausedUntil?->format(DateTimeInterface::ATOM),
            'lists' => array_map(function (Membership $membership): array {
                $list = $this->lists->get($membership->listId);

                return ['list' => $list instanceof MailingList ? $list->name : $membership->listId, 'status' => $membership->status->value, 'since' => $membership->changedAt->format(DateTimeInterface::ATOM)];
            }, $this->subscribers->memberships($subscriber->id)),
            'messages' => array_map(static fn (Delivery $delivery): array => [
                'campaign' => $delivery->campaignId,
                'template' => $delivery->template,
                'status' => $delivery->status->value,
                'sent_at' => $delivery->sentAt?->format(DateTimeInterface::ATOM),
            ], $this->deliveries->ofSubscriber($subscriber->id)),
            'clicks' => array_map(static fn (array $click): array => ['campaign' => $click['campaign'], 'link' => $click['link'], 'at' => $click['at']->format(DateTimeInterface::ATOM)], $this->engagement->recipientClicks($subscriber->id)),
        ];
    }
}

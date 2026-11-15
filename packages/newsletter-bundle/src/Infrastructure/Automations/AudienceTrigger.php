<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Automations;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\Trigger;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Model\MailingList;

/**
 * The triggers `newsletter.subscribed`, `newsletter.confirmed`, `newsletter.unsubscribed`, `newsletter.paused` and
 * `newsletter.resumed`: one per event type, narrowed to a list by its key when the definition names one. The run is
 * about the subscriber; its payload holds the subscriber's id, the list's key, the site and the external id.
 */
final readonly class AudienceTrigger implements Trigger
{
    public const array TYPES = [AudienceEventType::Subscribed, AudienceEventType::Confirmed, AudienceEventType::Unsubscribed, AudienceEventType::Paused, AudienceEventType::Resumed];

    public function __construct(
        private AudienceEventType $type,
        private MailingListRepository $lists,
    ) {
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('newsletter.'.$this->type->value, 'Newsletter: subscriber '.$this->type->value, [new OptionSpec('list', OptionType::Identifier, false, 'List key')]);
    }

    #[Override]
    public function events(): array
    {
        return [AudienceChanged::class];
    }

    #[Override]
    public function match(object $event, Options $options): ?TriggerMatch
    {
        if (!$event instanceof AudienceChanged || $this->type !== $event->type) {
            return null;
        }

        $list = null === $event->listId ? null : $this->lists->get($event->listId);
        $wanted = $options->string('list');

        if (null !== $wanted && (!$list instanceof MailingList || $list->key !== $wanted)) {
            return null;
        }

        return new TriggerMatch('newsletter:'.$event->id(), $event->subscriberId, Payload::of([
            'subscriber' => $event->subscriberId,
            'list' => $list?->key,
            'site' => $event->site,
            'external_id' => $event->externalId,
        ]));
    }
}

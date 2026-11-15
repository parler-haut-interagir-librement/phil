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
use Phil\NewsletterBundle\Application\Event\LinkClicked;

/**
 * The trigger `newsletter.link_clicked`: a subscriber clicked a link of a campaign that counts clicks per recipient,
 * narrowed to a campaign when the definition names one. A campaign that counts clicks in a total never starts it, and
 * no trigger reacts to the opening of a message.
 */
final readonly class LinkClickedTrigger implements Trigger
{
    public const string TYPE = 'newsletter.link_clicked';

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription(self::TYPE, 'Newsletter: link clicked', [new OptionSpec('campaign', OptionType::Identifier, false, 'Campaign')]);
    }

    #[Override]
    public function events(): array
    {
        return [LinkClicked::class];
    }

    #[Override]
    public function match(object $event, Options $options): ?TriggerMatch
    {
        if (!$event instanceof LinkClicked || (null !== $options->string('campaign') && $options->string('campaign') !== $event->campaignId)) {
            return null;
        }

        return new TriggerMatch(
            \sprintf('newsletter-click:%s:%d:%s:%d', $event->campaignId, $event->link, $event->subscriberId, $event->occurredAt->getTimestamp()),
            $event->subscriberId,
            Payload::of(['subscriber' => $event->subscriberId, 'campaign' => $event->campaignId, 'link' => $event->link]),
        );
    }
}

<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Notifier;

use Override;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Sends the alert through the chatter of `symfony/notifier` — a Discord, Slack, Mattermost or Matrix transport, named
 * as the notifier names it, or its default one. The message names the group by its title, its culprit, its count and
 * the start of its fingerprint, and the environment; the events stay in the store.
 */
final readonly class ChatterAlerter implements GroupAlerter
{
    public function __construct(
        private ChatterInterface $chatter,
        private ?string $transport = null,
        private ?string $environment = null,
    ) {
    }

    #[Override]
    public function alert(ErrorGroup $group, Occurrence $occurrence): void
    {
        $lines = [
            \sprintf('%s%s: %s', null === $this->environment ? '' : '['.$this->environment.'] ', Occurrence::First === $occurrence ? 'New error' : 'Regression', $group->title),
            null === $group->culprit ? null : 'In '.$group->culprit,
            \sprintf('Seen %d time(s)%s — group %s', $group->timesSeen, null === $group->lastRelease ? '' : ' on '.$group->lastRelease, substr($group->fingerprint, 0, 12)),
        ];

        $message = new ChatMessage(mb_substr(implode("\n", array_filter($lines, \is_string(...))), 0, 1800, 'UTF-8'));
        $this->chatter->send(null === $this->transport ? $message : $message->transport($this->transport));
    }
}

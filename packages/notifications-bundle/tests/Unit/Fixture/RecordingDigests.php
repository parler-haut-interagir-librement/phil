<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class RecordingDigests implements DigestSender
{
    /** @var list<array{RecipientInterface, DigestFrequency, list<RenderedNotification>, ?string}> */
    public array $sent = [];

    public function count(): int
    {
        return \count($this->sent);
    }

    #[Override]
    public function send(RecipientInterface $contact, DigestFrequency $frequency, array $notifications, ?string $locale): void
    {
        $this->sent[] = [$contact, $frequency, $notifications, $locale];
    }
}

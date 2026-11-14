<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->sent[] = $message;
    }
}

<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer that keeps the messages it is asked to send, or fails as told.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    /** @var (Closure(RawMessage): void)|null */
    public ?Closure $whileSending = null;

    #[Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->whileSending instanceof Closure) {
            ($this->whileSending)($message);
        }

        $this->sent[] = $message;
    }
}

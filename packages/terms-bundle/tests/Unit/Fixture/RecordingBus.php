<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The messages dispatched, recorded and never handled.
 */
final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return Envelope::wrap($message, $stamps);
    }
}

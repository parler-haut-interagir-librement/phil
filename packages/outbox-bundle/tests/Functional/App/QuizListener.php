<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Functional\App;

use Phil\Outbox\RecordedEvent;

/**
 * A listener of the application, which keeps the identifiers of the records it handled: a record delivered again is
 * handled once.
 */
final class QuizListener
{
    /** @var array<string, string> the quizzes, by record identifier */
    public array $handled = [];

    public function __invoke(RecordedEvent $event): void
    {
        $this->handled[$event->id->value] ??= $event->payload->string('quiz');
    }
}

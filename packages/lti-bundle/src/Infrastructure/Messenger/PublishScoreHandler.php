<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Messenger;

use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\LtiBundle\Application\Message\PublishScore;
use Phil\LtiBundle\Application\ScorePublisher;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Sends the pending score a message names. A refusal the platform will repeat — the platform no longer registered, a
 * status of 400 to 499 other than 408 and 429 — is not tried again; any other failure is.
 */
final readonly class PublishScoreHandler
{
    public function __construct(
        private ScorePublisher $publisher,
    ) {
    }

    public function __invoke(PublishScore $message): void
    {
        try {
            $this->publisher->send($message->id);
        } catch (UnknownRegistration|InvalidRequest $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } catch (ServiceFailed $serviceFailed) {
            $status = $serviceFailed->status();

            if ($status >= 400 && $status < 500 && !\in_array($status, [408, 429], true)) {
                throw new UnrecoverableMessageHandlingException($serviceFailed->getMessage(), 0, $serviceFailed);
            }

            throw $serviceFailed;
        }
    }
}

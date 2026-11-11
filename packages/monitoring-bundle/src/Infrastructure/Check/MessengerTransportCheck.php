<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Check;

use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * A Messenger transport answers how many messages it holds, degraded above its threshold — a queue piling up, or a
 * failure transport, whose threshold is usually 0, holding failed messages. A transport that cannot count is up.
 */
final readonly class MessengerTransportCheck implements HealthCheck
{
    public function __construct(
        private object $receiver,
        private string $name,
        private ?int $maxMessages = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        if (!$this->receiver instanceof MessageCountAwareInterface) {
            return CheckOutcome::up('Cannot count its messages.');
        }

        $count = $this->receiver->getMessageCount();

        if (null !== $this->maxMessages && $count > $this->maxMessages) {
            return CheckOutcome::degraded(\sprintf('%d messages waiting, over %d.', $count, $this->maxMessages));
        }

        return CheckOutcome::up(\sprintf('%d messages waiting.', $count));
    }
}

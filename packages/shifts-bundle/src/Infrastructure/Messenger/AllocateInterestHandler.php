<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Messenger;

use Phil\Shifts\Exception\ShiftRefused;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Message\AllocateInterest;
use Phil\ShiftsBundle\Application\Rosters;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Allocates the free places of a shift among the interested. A shift removed, closed or no longer allocated meanwhile
 * gets nothing, and a notice is logged with its identifier; a roster busy three times is thrown, and Messenger retries
 * the message.
 */
final readonly class AllocateInterestHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private Rosters $rosters,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(AllocateInterest $message): void
    {
        try {
            $this->rosters->allocate($message->shiftId);
        } catch (ShiftRefused $refused) {
            $this->logger->notice('The places of shift {shift} were not allocated: {reason}.', ['shift' => $message->shiftId, 'reason' => $refused->reason]);
        } catch (RequestRefused $refused) {
            if (RequestRefused::BUSY === $refused->reason) {
                throw $refused;
            }

            $this->logger->notice('The places of shift {shift} were not allocated: {reason}.', ['shift' => $message->shiftId, 'reason' => $refused->reason]);
        }
    }
}

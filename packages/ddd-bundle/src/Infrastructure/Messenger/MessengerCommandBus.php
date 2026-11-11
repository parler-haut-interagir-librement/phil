<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Messenger;

use Phil\DddBundle\Application\Bus\Command;
use Phil\DddBundle\Application\Bus\CommandBus;
use Phil\DddBundle\Domain\Exception\DomainError;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $handlerFailedException) {
            // Messenger wraps whatever the handler threw. A domain error must come out as is: the caller
            // catches it as `DomainError`, and a `HandlerFailedException` here would force every caller to
            // unwrap Messenger — that is, to know the bus.
            $domainError = $this->firstDomainError($handlerFailedException);

            throw $domainError ?? $handlerFailedException;
        }
    }

    private function firstDomainError(HandlerFailedException $exception): ?DomainError
    {
        foreach ($exception->getWrappedExceptions() as $wrapped) {
            if ($wrapped instanceof DomainError) {
                return $wrapped;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Messenger;

use Phil\DddBundle\Application\Bus\Query;
use Phil\DddBundle\Application\Bus\QueryBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    /**
     * @template TResult
     *
     * @param Query<TResult> $query
     *
     * @return TResult
     */
    public function ask(Query $query): mixed
    {
        try {
            /** @var TResult */
            return $this->handle($query);
        } catch (HandlerFailedException $handlerFailedException) {
            // A query has only one handler, so the wrapped exception is the only one and it comes out as is,
            // whatever its type. The command bus, by contrast, unwraps only the `DomainError`: a command may
            // have made several things fail, and only the domain error means something to the caller.
            throw $handlerFailedException->getPrevious() ?? $handlerFailedException;
        }
    }
}

<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture;

use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * The last middleware of a test bus: it keeps each envelope it is given, in place of the handlers.
 */
final class RecordingMiddleware implements MiddlewareInterface
{
    /** @var list<Envelope> */
    public array $envelopes = [];

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->envelopes[] = $envelope;

        return $envelope;
    }
}

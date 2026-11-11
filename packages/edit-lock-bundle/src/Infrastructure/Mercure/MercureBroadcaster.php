<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Mercure;

use DateTimeImmutable;
use Override;
use Phil\EditLockBundle\Application\Broadcaster;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Document;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Pushes presence and block saves to a Mercure hub, on one topic per resource, as Turbo Streams. Updates are private
 * unless configured otherwise: the application authorizes each writer to subscribe to the topics of the resources
 * they may edit. A hub that cannot be reached is logged and does not fail the lease or the save, which are kept.
 */
final readonly class MercureBroadcaster implements Broadcaster
{
    public const string PREFIX = 'urn:phil:edit-lock:';

    public function __construct(
        private HubInterface $hub,
        private TurboStreams $streams = new TurboStreams(),
        private string $prefix = self::PREFIX,
        private bool $private = true,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The topic of a resource.
     */
    public function topic(string $resource): string
    {
        return $this->prefix.$resource;
    }

    #[Override]
    public function presence(string $resource, array $leases, DateTimeImmutable $now): void
    {
        $this->publish($resource, $this->streams->presence($resource, $leases, $now));
    }

    #[Override]
    public function saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void
    {
        $this->publish($resource, $this->streams->saved($holder, $block, $list, $after));
    }

    #[Override]
    public function removed(string $resource, Holder $holder, string $blockId): void
    {
        $this->publish($resource, $this->streams->removed($holder, $blockId));
    }

    private function publish(string $resource, string $stream): void
    {
        try {
            $this->hub->publish(new Update($this->topic($resource), $stream, $this->private));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The edit lock update of "{resource}" could not be pushed to Mercure: {message}', [
                'resource' => $resource,
                'message' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}

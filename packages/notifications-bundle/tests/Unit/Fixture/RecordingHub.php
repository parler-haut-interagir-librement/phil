<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * A hub that keeps what is published to it, or fails when asked to.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    public function __construct(
        private readonly string $url = 'https://hub.test/.well-known/mercure',
        private readonly ProtocolVersion $version = ProtocolVersion::V1,
        public bool $failing = false,
        private readonly ?\RuntimeException $failure = null,
    ) {
    }

    #[Override]
    public function getPublicUrl(): string
    {
        return $this->url;
    }

    #[Override]
    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    #[Override]
    public function publish(Update $update): string
    {
        if ($this->failure instanceof \RuntimeException) {
            throw $this->failure;
        }

        if ($this->failing) {
            throw new RuntimeException('The hub is down.');
        }

        $this->updates[] = $update;

        return 'urn:uuid:'.\count($this->updates);
    }

    #[Override]
    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->version;
    }

    #[Override]
    public function getCookieName(): string
    {
        return 'mercure_access_token';
    }
}

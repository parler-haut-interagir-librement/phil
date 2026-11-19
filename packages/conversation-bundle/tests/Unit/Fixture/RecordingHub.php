<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * A hub that keeps what is published to it, or fails as a hub that cannot be reached.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    public function __construct(
        public bool $failing = false,
    ) {
    }

    #[Override]
    public function getPublicUrl(): string
    {
        return 'https://hub.test/.well-known/mercure';
    }

    #[Override]
    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    #[Override]
    public function publish(Update $update): string
    {
        if ($this->failing) {
            throw new RuntimeException('The hub is down.');
        }

        $this->updates[] = $update;

        return 'urn:uuid:'.\count($this->updates);
    }

    #[Override]
    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::V1;
    }

    #[Override]
    public function getCookieName(): string
    {
        return 'mercure_access_token';
    }
}

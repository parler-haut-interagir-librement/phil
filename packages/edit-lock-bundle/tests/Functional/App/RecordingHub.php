<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional\App;

use Override;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * A hub that keeps what is published to it.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

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

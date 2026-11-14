<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * Grants every lock, and notes the lifetime asked for each time one is taken or refreshed.
 */
final class RecordingLockStore implements PersistingStoreInterface
{
    /**
     * @var list<float>
     */
    public array $lifetimes = [];

    private bool $held = false;

    #[Override]
    public function save(Key $key): void
    {
        $this->held = true;
    }

    #[Override]
    public function delete(Key $key): void
    {
        $this->held = false;
    }

    #[Override]
    public function exists(Key $key): bool
    {
        return $this->held;
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->lifetimes[] = $ttl;
    }
}

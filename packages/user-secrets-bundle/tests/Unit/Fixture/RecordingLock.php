<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\UserSecretsBundle\Application\Contract\RefreshLock;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * A refresh lock that keeps the secrets it locked; what it runs before it is taken stands for another process that
 * held it meanwhile.
 */
final class RecordingLock implements RefreshLock
{
    /**
     * @var list<string>
     */
    public array $locked = [];

    public function __construct(
        private ?Closure $whileWaiting = null,
    ) {
    }

    #[Override]
    public function synchronized(Owner $owner, SecretName $name, Closure $critical): mixed
    {
        $whileWaiting = $this->whileWaiting;
        $this->whileWaiting = null;

        if ($whileWaiting instanceof Closure) {
            $whileWaiting();
        }

        $this->locked[] = $owner->value.'/'.$name->value;

        return $critical();
    }
}

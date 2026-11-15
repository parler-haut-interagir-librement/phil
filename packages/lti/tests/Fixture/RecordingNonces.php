<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Lti\Contract\Nonces;
use Phil\Lti\Security\InMemoryNonces;

/**
 * Nonces that keep the scope and the value of each use, then answer as the memory store does.
 */
final class RecordingNonces implements Nonces
{
    /** @var list<array{string, string}> */
    public array $used = [];

    public function __construct(
        private readonly InMemoryNonces $inner,
    ) {
    }

    #[Override]
    public function use(string $scope, string $value, DateTimeImmutable $expiresAt): bool
    {
        $this->used[] = [$scope, $value];

        return $this->inner->use($scope, $value, $expiresAt);
    }
}

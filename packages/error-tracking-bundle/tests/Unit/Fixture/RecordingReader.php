<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Throwable;

/**
 * A reader without groups that keeps what it was asked, or fails.
 */
final class RecordingReader implements ErrorGroupReader
{
    /**
     * @var list<array{?GroupStatus, int, int}>
     */
    public array $asked = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
    ) {
    }

    #[Override]
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->asked[] = [$status, $limit, $offset];

        return [];
    }

    #[Override]
    public function countGroups(?GroupStatus $status): int
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return 0;
    }

    #[Override]
    public function events(string $fingerprint, int $limit): array
    {
        return [];
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return 0;
    }
}

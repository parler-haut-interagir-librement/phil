<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;

final class RecordingPurger implements RetentionPurger
{
    /**
     * @var list<PurgeOrder>
     */
    public array $orders = [];

    public function __construct(
        private readonly string $store,
        private readonly int $purged = 3,
    ) {
    }

    #[Override]
    public function store(): string
    {
        return $this->store;
    }

    #[Override]
    public function purge(PurgeOrder $order): int
    {
        $this->orders[] = $order;

        return $this->purged;
    }
}

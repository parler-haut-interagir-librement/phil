<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Support;

use Override;
use Phil\CodeOfConduct\Contract\ResponsiblePeople;

final class FixedResponsible implements ResponsiblePeople
{
    /**
     * @param array<string, list<string>> $people by scope
     */
    public function __construct(
        public array $people = [],
    ) {
    }

    #[Override]
    public function of(string $scope): array
    {
        return $this->people[$scope] ?? [];
    }
}

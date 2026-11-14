<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Override;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;

final class FixedVisitor implements VisitorPreferences
{
    /**
     * @param array<string, bool|string> $values
     */
    public function __construct(
        public array $values = [],
    ) {
    }

    #[Override]
    public function values(): array
    {
        return $this->values;
    }
}

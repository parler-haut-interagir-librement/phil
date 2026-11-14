<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Override;
use Phil\SettingsBundle\Application\Contract\CurrentUser;

final class FixedUser implements CurrentUser
{
    public function __construct(
        public ?string $id = null,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->id;
    }
}

<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftOwner;

final class FixedOwner implements DraftOwner
{
    public function __construct(
        public ?string $user = 'ada',
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->user;
    }
}

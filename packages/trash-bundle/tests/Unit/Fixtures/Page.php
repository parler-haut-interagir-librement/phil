<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Fixtures;

final class Page
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $parent = null,
    ) {
    }
}

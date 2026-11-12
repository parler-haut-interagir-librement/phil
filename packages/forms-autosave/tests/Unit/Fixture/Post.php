<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

/**
 * A record a form edits, with an identifier.
 */
final class Post
{
    public ?string $title = null;

    public ?string $body = null;

    public function __construct(
        private readonly int|string|null $id = null,
    ) {
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
}

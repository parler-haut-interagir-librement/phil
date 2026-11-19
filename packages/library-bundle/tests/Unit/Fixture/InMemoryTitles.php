<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Fixture;

use Override;
use Phil\LibraryBundle\Domain\Contract\TitleRepository;
use Phil\LibraryBundle\Domain\Model\Title;

final class InMemoryTitles implements TitleRepository
{
    /** @var array<string, Title> */
    public array $titles = [];

    /** @var list<string> the titles saved, in order */
    public array $saved = [];

    #[Override]
    public function get(string $id): ?Title
    {
        return $this->titles[$id] ?? null;
    }

    #[Override]
    public function save(Title $title): void
    {
        $this->titles[$title->id] = $title;
        $this->saved[] = $title->id;
    }
}

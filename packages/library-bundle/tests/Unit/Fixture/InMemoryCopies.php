<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Fixture;

use Override;
use Phil\LibraryBundle\Domain\Contract\CopyRepository;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Title;

final class InMemoryCopies implements CopyRepository
{
    /** @var array<string, Copy> */
    public array $copies = [];

    public int $saves = 0;

    #[Override]
    public function get(string $id): ?Copy
    {
        return $this->copies[$id] ?? null;
    }

    #[Override]
    public function byBarcode(string $barcode): ?Copy
    {
        return array_find($this->copies, static fn (Copy $copy): bool => $barcode === $copy->barcode());
    }

    #[Override]
    public function ofTitle(Title $title, int $limit): array
    {
        $copies = array_values(array_filter($this->copies, static fn (Copy $copy): bool => $title === $copy->title()));
        usort($copies, static fn (Copy $a, Copy $b): int => $a->barcode() <=> $b->barcode());

        return \array_slice($copies, 0, $limit);
    }

    #[Override]
    public function save(Copy $copy): void
    {
        $this->copies[$copy->id] = $copy;
        ++$this->saves;
    }
}

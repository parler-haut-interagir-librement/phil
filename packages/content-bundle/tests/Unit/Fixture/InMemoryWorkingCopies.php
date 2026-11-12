<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * The working copies, kept in memory.
 */
final class InMemoryWorkingCopies implements WorkingCopyRepository
{
    /** @var list<WorkingCopy> */
    private array $kept = [];

    #[Override]
    public function findAutosave(ContentReference $content, Locale $locale, string $author): ?WorkingCopy
    {
        return array_find($this->of($content, $locale), static fn (WorkingCopy $copy): bool => $copy->isAutosave() && $author === $copy->author());
    }

    #[Override]
    public function findNamed(ContentReference $content, Locale $locale, string $name): ?WorkingCopy
    {
        return array_find($this->of($content, $locale), static fn (WorkingCopy $copy): bool => $name === $copy->name());
    }

    #[Override]
    public function findAllNamed(ContentReference $content, Locale $locale): array
    {
        return array_values(array_filter($this->of($content, $locale), static fn (WorkingCopy $copy): bool => !$copy->isAutosave()));
    }

    #[Override]
    public function findAllOf(ContentReference $content): array
    {
        return array_values(array_filter($this->kept, static fn (WorkingCopy $copy): bool => $copy->content()->equals($content)));
    }

    #[Override]
    public function add(WorkingCopy $copy): void
    {
        $this->kept[] = $copy;
    }

    #[Override]
    public function remove(WorkingCopy $copy): void
    {
        $this->kept = array_values(array_filter($this->kept, static fn (WorkingCopy $kept): bool => $kept !== $copy));
    }

    /**
     * @return list<WorkingCopy>
     */
    private function of(ContentReference $content, Locale $locale): array
    {
        return array_values(array_filter($this->findAllOf($content), static fn (WorkingCopy $copy): bool => $copy->locale()->equals($locale)));
    }
}

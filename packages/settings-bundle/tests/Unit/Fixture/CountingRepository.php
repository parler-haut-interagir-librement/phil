<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;

/**
 * A repository in memory that counts its reads and remembers its saves.
 */
final class CountingRepository implements SettingRepository
{
    public int $reads = 0;

    /** @var list<array{string, array<string, string>, list<string>, DateTimeImmutable}> */
    public array $saves = [];

    public function __construct(
        public readonly InMemorySettingRepository $inner = new InMemorySettingRepository(),
    ) {
    }

    #[Override]
    public function values(Subject $subject): array
    {
        ++$this->reads;

        return $this->inner->values($subject);
    }

    #[Override]
    public function save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void
    {
        $this->saves[] = [$subject->key(), $values, $removed, $at];
        $this->inner->save($subject, $values, $removed, $at);
    }

    #[Override]
    public function erase(Subject $subject): array
    {
        return $this->inner->erase($subject);
    }
}

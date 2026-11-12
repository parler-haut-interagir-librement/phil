<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;

/**
 * One translation unit of the memory, as a row identified by both languages and the fingerprint of the source: the
 * length of the plain source that fuzzy matches look up by, the source, the target and where it came from.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['source_language', 'target_language', 'length'])]
final class MemoryRecord
{
    public const string TABLE = 'phil_translation_memory';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'source_language', length: 35)]
        private readonly string $sourceLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'target_language', length: 35)]
        private readonly string $targetLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'fingerprint', length: 64)]
        private readonly string $fingerprint,
        #[ORM\Column(name: 'length')]
        private readonly int $length,
        #[ORM\Column(name: 'source', type: 'text')]
        private readonly string $source,
        #[ORM\Column(name: 'target', type: 'text')]
        private string $target,
        #[ORM\Column(name: 'origin', length: 255, nullable: true)]
        private ?string $origin,
        #[ORM\Column(name: 'updated_at')]
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function of(MemoryUnit $unit, DateTimeImmutable $now): self
    {
        return new self($unit->sourceLanguage->value, $unit->targetLanguage->value, $unit->fingerprint->value, $unit->length(), $unit->source, $unit->target, $unit->origin, $now);
    }

    /**
     * Takes the target and origin of a newer unit of the same source.
     */
    public function replace(MemoryUnit $unit, DateTimeImmutable $now): void
    {
        $this->target = $unit->target;
        $this->origin = $unit->origin;
        $this->updatedAt = $now;
    }

    public function unit(): MemoryUnit
    {
        return new MemoryUnit(LanguageTag::of($this->sourceLanguage), LanguageTag::of($this->targetLanguage), $this->source, $this->target, $this->origin);
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * The length of the plain source in characters.
     */
    public function length(): int
    {
        return $this->length;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

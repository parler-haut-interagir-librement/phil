<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure;

use Phil\CreationDisclosure\Exception\InvalidDisclosure;

/**
 * How a work was made, part by part: each part of the work — its text, its images, its audio, its video, its code —
 * declared with a digital source type, or left undeclared, which stays visible. Immutable: each declaration returns a
 * new disclosure.
 *
 * ```php
 * $book = CreationDisclosure::of(Part::Text, Part::Image)
 *     ->declare(Part::Text, DigitalSourceType::HumanEdits)
 *     ->declare(Part::Image, DigitalSourceType::TrainedAlgorithmicMedia);
 *
 * $book->generativeAi();          // GenerativeAi::Partial
 * $book->isFreeOfGenerativeAi();  // false
 * ```
 */
final readonly class CreationDisclosure
{
    /**
     * @param array<value-of<Part>, ?DigitalSourceType> $sources by part, in the order of `Part`
     */
    private function __construct(
        private array $sources,
    ) {
    }

    /**
     * A work made of these parts, none of them declared yet.
     */
    public static function of(Part ...$parts): self
    {
        return new self(self::ordered(array_fill_keys(array_map(static fn (Part $part): string => $part->value, $parts), null)));
    }

    /**
     * A disclosure read back from `toArray()`: each part's code, or `null` when it is undeclared. A code's address, as
     * XMP carries it, is read too.
     *
     * @param array<array-key, mixed> $stored
     *
     * @throws InvalidDisclosure when a key is not a part or a value is neither a code nor `null`
     */
    public static function fromArray(array $stored): self
    {
        $sources = [];

        foreach ($stored as $part => $code) {
            $part = Part::tryFrom((string) $part) ?? throw InvalidDisclosure::unknownPart((string) $part);

            if (null !== $code && !\is_string($code)) {
                throw InvalidDisclosure::notACode($part->value);
            }

            $sources[$part->value] = null === $code ? null : (str_contains($code, '/') ? DigitalSourceType::fromUri($code) : DigitalSourceType::fromCode($code));
        }

        return new self(self::ordered($sources));
    }

    /**
     * The same disclosure with a part declared, added when the work did not have it. A retired type is declared as its
     * replacement.
     */
    public function declare(Part $part, DigitalSourceType $type): self
    {
        return new self(self::ordered([...$this->sources, $part->value => $type->current()]));
    }

    /**
     * The same disclosure with a part back to undeclared, added when the work did not have it.
     */
    public function undeclare(Part $part): self
    {
        return new self(self::ordered([...$this->sources, $part->value => null]));
    }

    /**
     * The parts of the work, in the order of `Part`.
     *
     * @return list<Part>
     */
    public function parts(): array
    {
        return array_map(Part::from(...), array_keys($this->sources));
    }

    /**
     * The parts nobody declared.
     *
     * @return list<Part>
     */
    public function undeclared(): array
    {
        return array_map(Part::from(...), array_keys(array_filter($this->sources, static fn (?DigitalSourceType $type): bool => !$type instanceof DigitalSourceType)));
    }

    public function has(Part $part): bool
    {
        return \array_key_exists($part->value, $this->sources);
    }

    /**
     * The type a part was declared with; `null` when it is undeclared or not a part of the work.
     */
    public function sourceOf(Part $part): ?DigitalSourceType
    {
        return $this->sources[$part->value] ?? null;
    }

    /**
     * What one part owes to generative AI; `Undeclared` when it is undeclared or not a part of the work.
     */
    public function generativeAiOf(Part $part): GenerativeAi
    {
        return $this->sourceOf($part)?->generativeAi() ?? GenerativeAi::Undeclared;
    }

    /**
     * What the whole work owes to generative AI, combined from its parts.
     */
    public function generativeAi(): GenerativeAi
    {
        return GenerativeAi::combine(...array_map($this->generativeAiOf(...), $this->parts()));
    }

    /**
     * The AI-free view: every part declared, none of them generated, in whole or in part, or possibly.
     */
    public function isFreeOfGenerativeAi(): bool
    {
        return GenerativeAi::None === $this->generativeAi();
    }

    /**
     * Each part's code, `null` when undeclared, for a JSON column or an API.
     *
     * @return array<value-of<Part>, ?value-of<DigitalSourceType>>
     */
    public function toArray(): array
    {
        return array_map(static fn (?DigitalSourceType $type): ?string => $type?->value, $this->sources);
    }

    /**
     * @param array<value-of<Part>, ?DigitalSourceType> $sources
     *
     * @return array<value-of<Part>, ?DigitalSourceType>
     */
    private static function ordered(array $sources): array
    {
        $ordered = [];

        foreach (Part::cases() as $part) {
            if (\array_key_exists($part->value, $sources)) {
                $ordered[$part->value] = $sources[$part->value];
            }
        }

        return $ordered;
    }
}

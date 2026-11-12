<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure;

use Phil\CreationDisclosure\Exception\InvalidDisclosure;

/**
 * What a contest, a shop or a catalogue asks of the works it receives: the parts that must be declared and, when it
 * sets one, the most generative AI each of them may hold — `GenerativeAi::None` for an AI-free contest.
 *
 * ```php
 * $contest = DisclosureRequirement::of([Part::Text, Part::Image], GenerativeAi::None);
 * $contest->unmet($entry); // the parts undeclared, or holding generated content
 * ```
 */
final readonly class DisclosureRequirement
{
    /**
     * @param non-empty-list<Part> $parts
     */
    private function __construct(
        public array $parts,
        public ?GenerativeAi $atMost,
    ) {
    }

    /**
     * @param list<Part>        $parts  the parts every work must declare
     * @param GenerativeAi|null $atMost the most generative AI a declared part may hold; any when null
     *
     * @throws InvalidDisclosure when no part is named
     */
    public static function of(array $parts, ?GenerativeAi $atMost = null): self
    {
        $parts = array_values(array_filter(Part::cases(), static fn (Part $part): bool => \in_array($part, $parts, true)));

        return [] === $parts ? throw InvalidDisclosure::noPart() : new self($parts, $atMost);
    }

    /**
     * The required parts a disclosure leaves undeclared, or declares beyond the limit, in the order of `Part`.
     *
     * @return list<Part>
     */
    public function unmet(CreationDisclosure $disclosure): array
    {
        return array_values(array_filter($this->parts, function (Part $part) use ($disclosure): bool {
            $view = $disclosure->generativeAiOf($part);

            return GenerativeAi::Undeclared === $view || ($this->atMost instanceof GenerativeAi && !$view->isAtMost($this->atMost));
        }));
    }

    public function isMetBy(CreationDisclosure $disclosure): bool
    {
        return [] === $this->unmet($disclosure);
    }
}

<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

use Phil\Citations\Exception\InvalidReference;

/**
 * The name of a person, in its parts as CSL-JSON keeps them, or the literal name of an organisation.
 *
 * « Ludwig van Beethoven » is the family `Beethoven`, the given `Ludwig` and the dropping particle `van`; « Vincent van
 * Gogh » keeps `van` with the family as a non-dropping particle; « World Health Organization » is a literal.
 */
final readonly class Name
{
    /**
     * @throws InvalidReference a name with neither a family nor a literal
     */
    public function __construct(
        public ?string $family = null,
        public ?string $given = null,
        public ?string $droppingParticle = null,
        public ?string $nonDroppingParticle = null,
        public ?string $suffix = null,
        public ?string $literal = null,
    ) {
        if (null === $literal && null === $family) {
            throw InvalidReference::because('a name needs a family or a literal');
        }
    }

    public static function literal(string $literal): self
    {
        return new self(literal: $literal);
    }

    public function isLiteral(): bool
    {
        return null !== $this->literal;
    }

    /**
     * The family with its non-dropping particle, or the literal: « van Gogh », « World Health Organization ».
     */
    public function familyName(): string
    {
        return $this->literal ?? trim($this->nonDroppingParticle.' '.$this->family);
    }

    /**
     * The initials of the given names, each followed by a period, a hyphen kept between the parts of a compound
     * name: « J. A. », « J.-P. ».
     */
    public function initials(): string
    {
        $initials = [];

        foreach (array_filter(explode(' ', strtr((string) $this->given, "\t\n\r", '   ')), static fn (string $word): bool => '' !== $word) as $word) {
            $parts = array_map(
                static fn (string $part): string => mb_substr($part, 0, 1).'.',
                array_filter(explode('-', $word), static fn (string $part): bool => '' !== $part),
            );
            $initials[] = implode('-', $parts);
        }

        return implode(' ', $initials);
    }
}

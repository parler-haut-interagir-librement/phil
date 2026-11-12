<?php

declare(strict_types=1);

namespace Phil\Contact;

/**
 * How a culture writes and sorts the names of its people: which name comes first, which leading words of a particle
 * are left out of the sort key, whether names are sorted by the given name, and whether names written in Chinese,
 * Japanese or Korean characters are joined without a space.
 *
 * `NameConventions::for()` gives the conventions of the library; an application builds its own for a culture it knows
 * better.
 */
final readonly class NameConvention
{
    /**
     * @param list<string> $droppedParticles the leading words of a particle left out of the sort key, in lowercase:
     *                                       `de` sorts `de La Fontaine` under `La Fontaine`
     */
    public function __construct(
        public NameOrder $order = NameOrder::GivenFirst,
        public array $droppedParticles = [],
        public bool $dropsWholeParticle = false,
        public bool $sortsByGiven = false,
        public bool $joinsNativeScript = false,
    ) {
    }

    /**
     * What a particle keeps in the sort key: all of it, the words after a dropped leading word, or nothing.
     */
    public function sortedParticle(string $particle): string
    {
        if ($this->dropsWholeParticle) {
            return '';
        }

        $lower = mb_strtolower($particle);

        foreach ($this->droppedParticles as $word) {
            if ($lower === $word) {
                return '';
            }

            // An elided word, `d'`, is followed by the rest at once; another word, by a space.
            $elided = str_ends_with($word, "'") || str_ends_with($word, '’');

            if (str_starts_with($lower, $elided ? $word : $word.' ')) {
                return ltrim(substr($particle, \strlen($word)));
            }
        }

        return $particle;
    }
}

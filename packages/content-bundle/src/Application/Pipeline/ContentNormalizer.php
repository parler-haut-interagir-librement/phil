<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use LogicException;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Every normalizer over one merged row, their fragments combined into one array.
 *
 * Fragments are combined key by key, and **two normalizers writing the same key is refused** rather than
 * resolved by order: the second would silently hide the first, and which one wins would depend on the
 * order of the services in the container.
 */
final readonly class ContentNormalizer
{
    /**
     * @var array<Normalizer>
     */
    private array $normalizers;

    /**
     * @param iterable<Normalizer> $normalizers read once, here: a generator cannot be walked for a second row
     */
    public function __construct(iterable $normalizers)
    {
        $this->normalizers = [...$normalizers];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LogicException when two normalizers write the same key
     */
    public function normalize(DimensionContent $content): array
    {
        $normalized = [];

        foreach ($this->normalizers as $normalizer) {
            $fragment = $normalizer->normalize($content);
            $repeated = array_intersect_key($fragment, $normalized);

            if ([] !== $repeated) {
                throw new LogicException(\sprintf('The key "%s" is written by two normalizers; the second is %s.', array_key_first($repeated), $normalizer::class));
            }

            $normalized += $fragment;
        }

        return $normalized;
    }
}

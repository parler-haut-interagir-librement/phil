<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Form;

use Override;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Between tags and their text, separated by commas: each tag once, trimmed, the empty ones left out.
 *
 * @implements DataTransformerInterface<list<string>, string>
 */
final class TagsTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): string
    {
        return implode(', ', $value ?? []);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function reverseTransform(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value ?? '')), static fn (string $tag): bool => '' !== $tag)));
    }
}

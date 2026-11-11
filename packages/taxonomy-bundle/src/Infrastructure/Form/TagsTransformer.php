<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Infrastructure\Form;

use Override;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Tags as text separated by commas: `jeux, soirée`; the empty ones and the repeated ones left out. More than
 * `$maxTags` distinct tags, or one longer than a tag name may be, fail with `taxonomy.tags.too_many` or
 * `taxonomy.tags.too_long`, in the `validators` domain, whose `{{ max }}` is the limit.
 *
 * @implements DataTransformerInterface<list<string>, string>
 */
final readonly class TagsTransformer implements DataTransformerInterface
{
    public function __construct(
        private int $maxTags = 20,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        return \is_array($value) ? implode(', ', $value) : '';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function reverseTransform(mixed $value): array
    {
        $tags = [];

        foreach (explode(',', \is_string($value) ? $value : '') as $tag) {
            $tag = trim($tag);
            $key = mb_strtolower($tag);

            if ('' === $tag || isset($tags[$key])) {
                continue;
            }

            if (mb_strlen($tag) > Tag::MAX_LENGTH) {
                throw $this->failure('taxonomy.tags.too_long', Tag::MAX_LENGTH);
            }

            if (\count($tags) === $this->maxTags) {
                throw $this->failure('taxonomy.tags.too_many', $this->maxTags);
            }

            $tags[$key] = $tag;
        }

        return array_values($tags);
    }

    private function failure(string $message, int $max): TransformationFailedException
    {
        return new TransformationFailedException(\sprintf('%s (%d)', $message, $max), invalidMessage: $message, invalidMessageParameters: ['{{ max }}' => (string) $max]);
    }
}

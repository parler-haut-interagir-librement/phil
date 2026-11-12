<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application\Capability;

use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Domain\Capability\RelatedCapable;

/**
 * The related listings through the pipeline of content-bundle: `related` submitted as listing identifiers by
 * relation, kept on the shared row, carried by publishing, and normalized back. `Directory` checks the relations and
 * their listings before; here the shape alone is checked.
 */
final readonly class RelatedPipeline implements DataMapper, Merger, Normalizer
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$unlocalized instanceof RelatedCapable || !SubmittedData::has($data, 'related')) {
            return;
        }

        $unlocalized->setRelated(self::shaped(SubmittedData::map($data, 'related') ?? []));
    }

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof RelatedCapable && $source instanceof RelatedCapable && [] !== $source->getRelated()) {
            $target->setRelated($source->getRelated());
        }
    }

    public function normalize(DimensionContent $content): array
    {
        return $content instanceof RelatedCapable ? ['related' => $content->getRelated()] : [];
    }

    /**
     * Identifiers by relation, each once, trimmed, in the order given; their keys — a form's — are left behind.
     *
     * @param array<array-key, mixed> $related
     *
     * @return array<string, list<string>>
     *
     * @throws InvalidContent when a relation is not named or its identifiers are not strings
     */
    public static function shaped(array $related): array
    {
        $shaped = [];

        foreach ($related as $relation => $ids) {
            if (!\is_string($relation) || !\is_array($ids)) {
                throw InvalidContent::because('The related listings are lists of identifiers by relation.');
            }

            $shaped[$relation] = [];

            foreach ($ids as $id) {
                if (!\is_string($id)) {
                    throw InvalidContent::because('A related listing is named by its identifier.');
                }

                $id = trim($id);

                if ('' !== $id && !\in_array($id, $shaped[$relation], true)) {
                    $shaped[$relation][] = $id;
                }
            }
        }

        return $shaped;
    }
}

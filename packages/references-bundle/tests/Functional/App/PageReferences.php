<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\ReferencesBundle\Application\Link;
use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Image;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Page;
use Phil\Resource\ResourceReference;

/**
 * The links of a page's blocks to images, and the images others use.
 */
final readonly class PageReferences implements ReferenceCollector, ReferenceTarget
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(object $source): bool
    {
        return $source instanceof Page;
    }

    public function sourceOf(object $source): ResourceReference
    {
        \assert($source instanceof Page);

        return ResourceReference::of('page', $source->id);
    }

    public function collect(object $source): iterable
    {
        \assert($source instanceof Page);

        foreach ($source->blocks as $index => $block) {
            if (isset($block['image'])) {
                yield new Link('blocks.'.$index.'.image', ResourceReference::of('image', $block['image']));
            }
        }
    }

    public function kind(): string
    {
        return 'image';
    }

    public function exists(string $id): bool
    {
        return null !== $this->entityManager->find(Image::class, $id);
    }

    public function referenceOf(object $resource): ?ResourceReference
    {
        return $resource instanceof Image ? ResourceReference::of('image', $resource->id) : null;
    }
}

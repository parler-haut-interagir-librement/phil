<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Captured;
use Phil\TrashBundle\Application\TrashHandler;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Tests\Functional\App\Entity\Article;

/**
 * Articles in their table, under sections that are articles too.
 */
final readonly class ArticleHandler implements TrashHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resourceKey(): string
    {
        return 'article';
    }

    public function supports(object $resource): bool
    {
        return $resource instanceof Article;
    }

    public function capture(object $resource): Captured
    {
        \assert($resource instanceof Article);

        return new Captured($resource->id, $resource->title, ['title' => $resource->title], null === $resource->section ? null : ResourceReference::of('article', $resource->section));
    }

    public function remove(object $resource): void
    {
        $this->entityManager->remove($resource);
    }

    public function parentExists(ResourceReference $parent): bool
    {
        return null !== $this->entityManager->find(Article::class, $parent->id);
    }

    public function restore(TrashItem $item, ?ResourceReference $parent): Article
    {
        $title = $item->payload()['title'] ?? '';
        \assert(\is_string($title));
        $article = new Article($item->resource()->id, $title, $parent?->id);
        $this->entityManager->persist($article);

        return $article;
    }

    public function purge(TrashItem $item): void
    {
    }
}

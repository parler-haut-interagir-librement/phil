<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Tests\Functional\App\Entity\Page;

final readonly class Pages implements DocumentProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function index(): string
    {
        return 'pages';
    }

    public function supports(object $resource): bool
    {
        return $resource instanceof Page;
    }

    public function document(object $resource): Document
    {
        \assert($resource instanceof Page);

        return Document::of('pages', $resource->id, ['title' => $resource->title], ['locale' => $resource->locale]);
    }

    public function id(object $resource): string
    {
        \assert($resource instanceof Page);

        return $resource->id;
    }

    public function all(): iterable
    {
        return $this->entityManager->getRepository(Page::class)->findAll();
    }
}

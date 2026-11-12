<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Tests\Functional\App\Entity\Note;

final readonly class Notes implements DocumentProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function index(): string
    {
        return 'notes';
    }

    public function supports(object $resource): bool
    {
        return $resource instanceof Note;
    }

    public function document(object $resource): Document
    {
        return Document::of('notes', $this->id($resource), ['text' => $resource instanceof Note ? $resource->text : '']);
    }

    public function id(object $resource): string
    {
        \assert($resource instanceof Note && null !== $resource->id);

        return (string) $resource->id;
    }

    public function all(): iterable
    {
        return $this->entityManager->getRepository(Note::class)->findAll();
    }
}

<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;
use Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity\Page;

final readonly class PageSubjects implements SubjectResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(ResourceReference $resource): ?object
    {
        return 'page' === $resource->key ? $this->entityManager->find(Page::class, $resource->id) : null;
    }

    public function saved(object $subject): void
    {
        $this->entityManager->flush();
    }
}

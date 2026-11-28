<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;

/**
 * The update plans in the table `phil_sites_console_update_plan`.
 */
final readonly class DoctrineUpdatePlanRepository implements UpdatePlanRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?UpdatePlan
    {
        return $this->entityManager->find(UpdatePlan::class, $id);
    }

    #[Override]
    public function save(UpdatePlan $plan): void
    {
        $this->entityManager->persist($plan);
        $this->entityManager->flush();
    }

    #[Override]
    public function recent(int $limit): array
    {
        return $this->entityManager->getRepository(UpdatePlan::class)->findBy([], ['id' => 'DESC'], max(1, $limit));
    }
}

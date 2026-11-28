<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Model\Site;

/**
 * The sites in the table `phil_sites_console_site`.
 */
final readonly class DoctrineSiteRepository implements SiteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Site
    {
        return $this->entityManager->find(Site::class, $id);
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(Site::class)->findBy([], ['id' => 'ASC']);
    }

    #[Override]
    public function save(Site $site): void
    {
        $this->entityManager->persist($site);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Site $site): void
    {
        $this->entityManager->remove($site);
        $this->entityManager->flush();
    }
}

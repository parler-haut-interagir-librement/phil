<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;

/**
 * The pushes in the table `phil_sites_console_push`.
 */
final readonly class DoctrineConfigurationPushRepository implements ConfigurationPushRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?ConfigurationPush
    {
        return $this->entityManager->find(ConfigurationPush::class, $id);
    }

    #[Override]
    public function save(ConfigurationPush $push): void
    {
        $this->entityManager->persist($push);
        $this->entityManager->flush();
    }

    #[Override]
    public function recent(int $limit): array
    {
        return $this->entityManager->getRepository(ConfigurationPush::class)->findBy([], ['id' => 'DESC'], max(1, $limit));
    }
}

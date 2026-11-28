<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;

/**
 * The journal in the table `phil_sites_console_journal`.
 */
final readonly class DoctrineJournalRepository implements JournalRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function append(JournalEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    #[Override]
    public function recent(int $limit, ?string $siteId = null): array
    {
        return $this->entityManager->getRepository(JournalEntry::class)->findBy(null === $siteId ? [] : ['siteId' => $siteId], ['id' => 'DESC'], max(1, $limit));
    }
}

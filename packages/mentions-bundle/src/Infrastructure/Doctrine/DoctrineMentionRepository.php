<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;

/**
 * The mentions in the table `phil_mention`, read through the entity manager. A change runs in one transaction: the rows
 * removed are deleted by identifier, the rows added inserted by an upsert ({@see MentionInsert}) that keeps the row of
 * a member already mentioned, so that two requests recording the same subject at once add the member once and only
 * the one that inserted the row reports it.
 */
final readonly class DoctrineMentionRepository implements MentionRepository
{
    private const array TYPES = [Types::STRING, Types::STRING, Types::STRING, Types::STRING, Types::DATETIME_IMMUTABLE];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->entityManager->getRepository(RecordedMention::class)->findBy(['subject' => $subject->toString()], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function ofMember(ResourceReference $member, int $limit): array
    {
        return $this->entityManager->getRepository(RecordedMention::class)->findBy(['member' => $member->toString()], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    #[Override]
    public function change(array $added, array $removed): array
    {
        return $this->entityManager->getConnection()->transactional(function (Connection $connection) use ($added, $removed): array {
            foreach ($removed as $mention) {
                $connection->delete(RecordedMention::TABLE, ['id' => $mention->id]);
                $this->entityManager->detach($mention);
            }

            $insert = MentionInsert::sql($connection->getDatabasePlatform());

            return array_values(array_filter($added, static fn (RecordedMention $mention): bool => 1 === $connection->executeStatement(
                $insert,
                [$mention->id, $mention->subject()->toString(), $mention->member()->toString(), $mention->author()->toString(), $mention->createdAt()],
                self::TYPES,
            )));
        });
    }

    #[Override]
    public function forget(ResourceReference $subject): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(RecordedMention::class, 'm')
            ->where('m.subject = :subject')
            ->setParameter('subject', $subject->toString())
            ->getQuery()
            ->execute();
    }
}

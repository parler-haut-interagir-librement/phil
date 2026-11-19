<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\Repository\PersonalFeedTokenRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * The secrets of members' feeds with Doctrine, found by their digest; a member's old token is deleted with DBAL before
 * the new one is written, in one transaction.
 */
final readonly class DoctrinePersonalFeedTokenRepository implements PersonalFeedTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function replace(PersonalFeedToken $token): void
    {
        $this->entityManager->wrapInTransaction(function () use ($token): void {
            $old = $this->ofMember($token->member());

            if ($old instanceof PersonalFeedToken) {
                $this->entityManager->remove($old);
                $this->entityManager->flush();
            }

            $this->entityManager->persist($token);
            $this->entityManager->flush();
        });
    }

    #[Override]
    public function ofMember(Member $member): ?PersonalFeedToken
    {
        return $this->entityManager->getRepository(PersonalFeedToken::class)->findOneBy(['member' => $member->toString()]);
    }

    #[Override]
    public function byDigest(string $digest): ?PersonalFeedToken
    {
        return $this->entityManager->getRepository(PersonalFeedToken::class)->findOneBy(['digest' => $digest]);
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        $token = $this->ofMember($member);

        if ($token instanceof PersonalFeedToken) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
        }
    }
}

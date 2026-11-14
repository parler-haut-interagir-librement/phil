<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Memory;

use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The mentions in memory, for tests and examples. The bundle tags the store `kernel.reset`: it is emptied between two
 * requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one, nor another member. A
 * test that reads what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryMentionRepository implements MentionRepository, ResetInterface
{
    /** @var array<string, RecordedMention> */
    private array $mentions = [];

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->sorted(array_filter($this->mentions, static fn (RecordedMention $mention): bool => $mention->subject()->equals($subject)));
    }

    #[Override]
    public function ofMember(ResourceReference $member, int $limit): array
    {
        $mentions = $this->sorted(array_filter($this->mentions, static fn (RecordedMention $mention): bool => $mention->member()->equals($member)));

        return \array_slice(array_reverse($mentions), 0, $limit);
    }

    #[Override]
    public function change(array $added, array $removed): array
    {
        foreach ($removed as $mention) {
            unset($this->mentions[$mention->id]);
        }

        $kept = [];

        foreach ($added as $mention) {
            if ([] === $this->ofSubjectAndMember($mention)) {
                $this->mentions[$mention->id] = $mention;
                $kept[] = $mention;
            }
        }

        return $kept;
    }

    #[Override]
    public function forget(ResourceReference $subject): void
    {
        $this->mentions = array_filter($this->mentions, static fn (RecordedMention $mention): bool => !$mention->subject()->equals($subject));
    }

    /**
     * @return array<string, RecordedMention>
     */
    private function ofSubjectAndMember(RecordedMention $added): array
    {
        return array_filter($this->mentions, static fn (RecordedMention $mention): bool => $mention->subject()->equals($added->subject()) && $mention->member()->equals($added->member()));
    }

    #[Override]
    public function reset(): void
    {
        $this->mentions = [];
    }

    /**
     * The mentions oldest first, by their second then their identifier, as the Doctrine store reads them.
     *
     * @param array<string, RecordedMention> $mentions
     *
     * @return list<RecordedMention>
     */
    private function sorted(array $mentions): array
    {
        usort($mentions, static fn (RecordedMention $a, RecordedMention $b): int => [$a->createdAt()->getTimestamp(), $a->id] <=> [$b->createdAt()->getTimestamp(), $b->id]);

        return $mentions;
    }
}

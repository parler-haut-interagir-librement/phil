<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Domain\Contract\MemberNoteRepository;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;
use Psr\Clock\ClockInterface;

/**
 * The notes of the moderation on a member, written and read by moderators only.
 */
final readonly class MemberNotes
{
    public function __construct(
        private MemberNoteRepository $notes,
        private CurrentModerator $currentModerator,
        private ClockInterface $clock,
    ) {
    }

    public function write(MemberId $member, string $body): MemberNote
    {
        $note = new MemberNote(Identifiers::generate(), $member, $this->moderator()->member, $body, $this->clock->now());
        $this->notes->add($note);

        return $note;
    }

    /**
     * @return list<MemberNote> the latest first
     *
     * @phpstan-impure
     */
    public function about(MemberId $member): array
    {
        $this->moderator();

        return $this->notes->about($member);
    }

    public function remove(string $id): bool
    {
        $this->moderator();

        return $this->notes->remove($id);
    }

    private function moderator(): Moderator
    {
        return $this->currentModerator->moderator() ?? throw ModerationRefused::because('Only a moderator reads and writes the notes on a member.');
    }
}

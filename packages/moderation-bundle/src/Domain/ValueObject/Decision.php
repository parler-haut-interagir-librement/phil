<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

/**
 * One decision on a target: the action, the moderator and the role they decided under, the reason the public journal
 * shows, the note only the moderation reads, and the author of the content at that moment.
 *
 * The public journal shows the role, never the moderator.
 */
final readonly class Decision
{
    public const int MAX_REASON_LENGTH = 500;

    public const int MAX_NOTE_LENGTH = 2000;

    public ?string $publicReason;

    public ?string $note;

    public function __construct(
        public string $id,
        public TargetReference $target,
        public DecisionAction $action,
        public ?MemberId $moderator,
        public string $role,
        ?string $publicReason,
        ?string $note,
        public ?MemberId $author,
        public DateTimeImmutable $decidedAt,
        public bool $public = true,
    ) {
        Identifiers::check($id, 'A decision');
        Identifiers::code($role, 'a role');
        $this->publicReason = Identifiers::text($publicReason, self::MAX_REASON_LENGTH, 'A public reason');
        $this->note = Identifiers::text($note, self::MAX_NOTE_LENGTH, 'A note');
    }
}

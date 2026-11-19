<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;

/**
 * The reactions' part of an access or erasure request, the data subject being the member: the export lists the
 * reactions, private ones too; the erasure deletes them, and the counters go down with them.
 */
final readonly class ReactionsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'reactions';

    public function __construct(
        private ReactionRepository $reactions,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $reactions = $this->reactions->ofMember($subject->value);

        return [] === $reactions ? [] : ['reactions' => array_map(static fn (Reaction $reaction): array => [
            'content' => $reaction->content,
            'code' => $reaction->code(),
            'reacted_at' => $reaction->reactedAt()->format(DateTimeInterface::ATOM),
        ], $reactions)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->reactions->eraseMember($subject->value);

        return ErasureReport::of(self::MODULE, [Reaction::TABLE => ErasureEffect::Deleted]);
    }
}

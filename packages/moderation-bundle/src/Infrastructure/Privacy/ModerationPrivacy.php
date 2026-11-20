<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Contract\MemberNoteRepository;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\Contract\StatementRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The moderation's part of an access or erasure request, for the member the subject names: the reports they made, the
 * decisions on their content and those they took as a moderator, the statements of reasons addressed to them, the notes
 * of the moderation on them, and when they left the sandbox. The export never names another member: no moderator, no
 * reporter, no author of a note. The incidents a member started or lifted are answered by `phil/incident-mode-bundle`.
 *
 * Erasing deletes the notes on the member, the statements addressed to them and the record of the sandbox, and takes
 * the member out of the reports, the decisions and the notes they wrote, which stay without them.
 */
final readonly class ModerationPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'moderation';

    /** The statements read at once for an export. */
    public const int PAGE = 100;

    public function __construct(
        private ReportRepository $reports,
        private DecisionRepository $decisions,
        private MemberNoteRepository $notes,
        private SandboxRepository $sandbox,
        private ?StatementRepository $statements = null,
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
        $member = $this->member($subject);

        if (!$member instanceof MemberId) {
            return [];
        }

        $data = array_filter([
            'reports' => array_map(static fn (Report $report): array => [
                'target_type' => $report->target->type,
                'target_id' => $report->target->id,
                'reason' => $report->reason,
                'comment' => $report->comment,
                'reported_at' => $report->reportedAt->format(DateTimeInterface::ATOM),
                'closed' => !$report->isOpen(),
            ], $this->reports->madeBy($member)),
            'decisions_about_my_content' => array_map(static fn (Decision $decision): array => [
                'target_type' => $decision->target->type,
                'target_id' => $decision->target->id,
                'action' => $decision->action->value,
                'role' => $decision->role,
                'reason' => $decision->publicReason,
                'decided_at' => $decision->decidedAt->format(DateTimeInterface::ATOM),
            ], $this->decisions->about($member)),
            'decisions_taken' => array_map(static fn (Decision $decision): array => [
                'target_type' => $decision->target->type,
                'target_id' => $decision->target->id,
                'action' => $decision->action->value,
                'role' => $decision->role,
                'decided_at' => $decision->decidedAt->format(DateTimeInterface::ATOM),
            ], $this->decisions->takenBy($member)),
            'statements_addressed_to_me' => $this->statements($member),
            'notes_about_me' => array_map(static fn (MemberNote $note): array => [
                'note' => $note->body,
                'written_at' => $note->writtenAt->format(DateTimeInterface::ATOM),
            ], $this->notes->about($member)),
        ], static fn (array $records): bool => [] !== $records);

        $cleared = $this->sandbox->clearedAt($member);

        if ($cleared instanceof DateTimeImmutable) {
            $data['sandbox_left_at'] = $cleared->format(DateTimeInterface::ATOM);
        }

        return $data;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);

        if ($member instanceof MemberId) {
            $this->reports->forgetReporter($member);
            $this->decisions->forget($member);
            $this->notes->erase($member);
            $this->sandbox->forget($member);
            $this->statements?->erase($member);
        }

        return ErasureReport::of(self::MODULE, array_filter([
            ModerationTables::REPORTS => ErasureEffect::Pseudonymised,
            ModerationTables::DECISIONS => ErasureEffect::Pseudonymised,
            ModerationTables::NOTES => ErasureEffect::Deleted,
            ModerationTables::SANDBOX => ErasureEffect::Deleted,
            ModerationTables::STATEMENTS => $this->statements instanceof StatementRepository ? ErasureEffect::Deleted : null,
        ]));
    }

    private function member(DataSubject $subject): ?MemberId
    {
        try {
            return MemberId::of($subject->value);
        } catch (InvalidModeration) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statements(MemberId $member): array
    {
        if (!$this->statements instanceof StatementRepository) {
            return [];
        }

        $records = [];

        for ($offset = 0, $page = []; 0 === $offset || self::PAGE === \count($page); $offset += self::PAGE) {
            $page = $this->statements->addressedTo($member, self::PAGE, $offset);

            foreach ($page as $statement) {
                $records[] = [
                    'target_type' => $statement->target->type,
                    'target_id' => $statement->target->id,
                    'action' => $statement->action->value,
                    'reason' => $statement->reason,
                    'ground' => $statement->ground->value,
                    'reference' => $statement->reference,
                    'explanation' => $statement->explanation,
                    'prompted_by_a_report' => $statement->prompted,
                    'automated_detection' => $statement->automatedDetection,
                    'issued_at' => $statement->issuedAt->format(DateTimeInterface::ATOM),
                ];
            }
        }

        return $records;
    }
}

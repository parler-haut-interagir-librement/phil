<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\ReportSource;

/**
 * One report on a target: where it comes from, its reason, the member who made it when a member did, their comment, and
 * the decision that closed it once one did.
 */
final readonly class Report
{
    public const int MAX_COMMENT_LENGTH = 1000;

    public ?string $comment;

    public function __construct(
        public string $id,
        public TargetReference $target,
        public ReportSource $source,
        public string $reason,
        public ?MemberId $reporter,
        ?string $comment,
        public DateTimeImmutable $reportedAt,
        public ?string $decisionId = null,
    ) {
        Identifiers::check($id, 'A report');
        Identifiers::code($reason, 'a reason');
        $this->comment = Identifiers::text($comment, self::MAX_COMMENT_LENGTH, 'A comment');

        if (null !== $decisionId) {
            Identifiers::check($decisionId, 'A decision');
        }
    }

    public static function file(TargetReference $target, ReportSource $source, string $reason, ?MemberId $reporter, ?string $comment, DateTimeImmutable $at): self
    {
        return new self(Identifiers::generate(), $target, $source, $reason, $reporter, $comment, $at);
    }

    public function isOpen(): bool
    {
        return null === $this->decisionId;
    }
}

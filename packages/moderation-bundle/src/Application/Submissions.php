<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\SandboxPolicy;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\Event\ContentHeld;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The hook a package calls when a member creates a content — a question, a forum post, a listing, a comment —, before
 * it publishes it: the watched words read its text, and the sandbox of the first post holds it when the member has no
 * approved content yet.
 *
 * A held content waits in the queue; the package publishes it when a moderator approves it (`Approve`) and removes it
 * when they remove it. A blocked content is refused, and nothing reaches the queue.
 */
final readonly class Submissions
{
    public function __construct(
        private Reports $reports,
        private WordScreen $words,
        private SandboxPolicy $sandbox,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function submit(TargetReference $target, MemberId $author, ?string $text = null): Submission
    {
        $screening = null === $text ? Screening::allow('') : $this->words->screen($text);
        $kept = null === $text ? null : $screening->text;
        $words = [] === $screening->matches ? null : implode(', ', $screening->matches);

        if (ScreeningVerdict::Block === $screening->verdict) {
            return new Submission(ScreeningVerdict::Block, $kept);
        }

        if (ScreeningVerdict::Hold === $screening->verdict) {
            $this->hold($target, ReportSource::WatchedWords, Reasons::WATCHED_WORDS, $words);

            return new Submission(ScreeningVerdict::Hold, $kept);
        }

        if ($this->sandbox->holds($author, $target->type)) {
            $this->hold($target, ReportSource::Sandbox, Reasons::FIRST_POST, $words);

            return new Submission(ScreeningVerdict::Hold, $kept);
        }

        if (ScreeningVerdict::Flag === $screening->verdict) {
            $this->reports->flag($target, ReportSource::WatchedWords, Reasons::WATCHED_WORDS, null, $words);

            return new Submission(ScreeningVerdict::Flag, $kept);
        }

        return new Submission(ScreeningVerdict::Allow, $kept);
    }

    private function hold(TargetReference $target, ReportSource $source, string $reason, ?string $words): void
    {
        $report = $this->reports->flag($target, $source, $reason, null, $words);
        $this->events?->dispatch(new ContentHeld($report));
    }
}

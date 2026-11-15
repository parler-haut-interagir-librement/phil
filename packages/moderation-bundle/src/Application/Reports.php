<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Event\ReportFiled;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The one entrance of the queue: a member reports a content of any registered type, and the bundle files its own
 * reports for what the watched words, the sandbox and the trusted members hold or hide.
 */
final readonly class Reports
{
    public function __construct(
        private ReportRepository $reports,
        private ModerationTargets $targets,
        private Reasons $reasons,
        private CurrentMember $currentMember,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * The current member reports the target. A member reports a target once while it is open: a second report gives
     * back the first.
     *
     * @throws ModerationRefused       when nobody is signed in or the reason is not in the catalogue
     * @throws Exception\UnknownTarget when no package registered the type
     */
    public function report(TargetReference $target, string $reason, ?string $comment = null): Report
    {
        $reporter = $this->currentMember->member() ?? throw ModerationRefused::because('Only a signed-in member reports a content.');

        if (!$this->reasons->has($reason)) {
            throw ModerationRefused::because(\sprintf('"%s" is not a reason of the catalogue.', $reason));
        }

        $this->targets->get($target->type);

        return $this->reports->openBy($target, $reporter) ?? $this->file($target, ReportSource::Member, $reason, $reporter, $comment);
    }

    /**
     * Files a report the bundle makes itself.
     */
    public function flag(TargetReference $target, ReportSource $source, string $reason, ?MemberId $by = null, ?string $comment = null): Report
    {
        return $this->file($target, $source, $reason, $by, $comment);
    }

    private function file(TargetReference $target, ReportSource $source, string $reason, ?MemberId $reporter, ?string $comment): Report
    {
        $report = Report::file($target, $source, $reason, $reporter, $comment, $this->clock->now());
        $this->reports->add($report);
        $this->events?->dispatch(new ReportFiled($report));

        return $report;
    }
}

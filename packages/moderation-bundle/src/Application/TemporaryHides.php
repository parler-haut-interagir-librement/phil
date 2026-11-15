<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Application\Event\ContentHiddenByMember;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A trusted member — of a high trust level — hides a content until the moderation reviews it: the content is hidden at
 * once, and a report waits in the queue, where a moderator restores it or confirms it.
 */
final readonly class TemporaryHides
{
    public function __construct(
        private Reports $reports,
        private ModerationTargets $targets,
        private TrustedHiders $trusted,
        private CurrentMember $currentMember,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @phpstan-impure
     */
    public function mayHide(): bool
    {
        $member = $this->currentMember->member();

        return $member instanceof MemberId && $this->trusted->canHide($member);
    }

    /**
     * @throws ModerationRefused when the current member is not trusted to hide
     */
    public function hide(TargetReference $target, ?string $comment = null): Report
    {
        $member = $this->currentMember->member();

        if (!$member instanceof MemberId || !$this->trusted->canHide($member)) {
            throw ModerationRefused::because('Only a trusted member hides a content.');
        }

        $this->targets->get($target->type)->apply($target->id, DecisionAction::Hide);
        $report = $this->reports->flag($target, ReportSource::TrustedHide, Reasons::HIDDEN_BY_MEMBER, $member, $comment);
        $this->events?->dispatch(new ContentHiddenByMember($report));

        return $report;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A moderator decides on a target: the target's package applies the action, the reports open on it are closed with the
 * decision, and the history keeps it. Approving the first post of a member takes them out of the sandbox.
 *
 * A decision that restricts the author — hide, remove, warn — comes with a statement of reasons addressed to them: its
 * grounds are checked before the action is applied, so that a decision refused for want of them changes nothing.
 */
final readonly class Decisions
{
    public function __construct(
        private DecisionRepository $decisions,
        private ReportRepository $reports,
        private SandboxRepository $sandbox,
        private ModerationTargets $targets,
        private CurrentModerator $currentModerator,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private ?StatementsOfReasons $statements = null,
    ) {
    }

    /**
     * @param bool     $public  whether the public journal shows the decision, with the role and never the moderator
     * @param ?Grounds $grounds the reason and the facts of a decision that restricts the author, which their statement
     *                          of reasons tells them
     *
     * @throws ModerationRefused       when nobody is a moderator, or their role does not cover the type
     * @throws Exception\UnknownTarget when no package registered the type
     * @throws InvalidModeration       when a decision that restricts the author lacks its grounds, or a field is refused
     */
    public function decide(TargetReference $target, DecisionAction $action, ?string $publicReason = null, ?string $note = null, bool $public = true, ?Grounds $grounds = null): Decision
    {
        $moderator = $this->moderatorFor($target->type);
        $adapter = $this->targets->get($target->type);
        $author = $adapter->describe($target->id)?->author;
        $open = $this->reports->openOn($target);
        $decision = new Decision(Identifiers::generate(), $target, $action, $moderator->member, $moderator->role->name, $publicReason, $note, $author, $this->clock->now(), $public);
        $statement = $this->statements?->draft($decision, $grounds, $open);

        if ($action->changesTarget()) {
            $adapter->apply($target->id, $action);
        }

        $this->decisions->add($decision);
        $this->reports->close($target, $decision->id);

        if (DecisionAction::Approve === $action && $author instanceof MemberId && array_any($open, static fn (Report $report): bool => ReportSource::Sandbox === $report->source)) {
            $this->sandbox->clear($author, $decision->decidedAt);
        }

        $this->events?->dispatch(new DecisionTaken($decision));

        if ($statement instanceof StatementOfReasons) {
            $this->statements->issue($statement);
        }

        return $decision;
    }

    /**
     * Whether the current member may decide on targets of this type.
     *
     * @phpstan-impure
     */
    public function mayDecide(string $type): bool
    {
        $moderator = $this->currentModerator->moderator();

        return $moderator instanceof Moderator && $moderator->role->covers($type);
    }

    /**
     * @return list<Decision> the latest first
     *
     * @phpstan-impure
     */
    public function on(TargetReference $target): array
    {
        return $this->decisions->on($target);
    }

    /**
     * @return list<Decision> the decisions on content of the member, the latest first
     *
     * @phpstan-impure
     */
    public function about(MemberId $author): array
    {
        return $this->decisions->about($author);
    }

    private function moderatorFor(string $type): Moderator
    {
        $moderator = $this->currentModerator->moderator() ?? throw ModerationRefused::because('Only a moderator decides.');

        if (!$moderator->role->covers($type)) {
            throw ModerationRefused::because(\sprintf('The role "%s" does not decide on "%s".', $moderator->role->name, $type));
        }

        return $moderator;
    }
}

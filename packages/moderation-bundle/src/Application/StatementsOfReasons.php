<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\ModerationBundle\Domain\Contract\StatementRepository;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The statements of reasons: drafted with a decision that restricts the author of a content, before its action is
 * applied, so that a decision without its grounds changes nothing; issued once the decision is kept; read by the member
 * they are addressed to, and by nobody else.
 */
final readonly class StatementsOfReasons
{
    public function __construct(
        private StatementRepository $statements,
        private Reasons $reasons,
        private ReasonGrounds $grounds,
        private CurrentMember $currentMember,
        private Redress $redress = new Redress(),
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * The statement the decision comes with, or `null` when it restricts nobody: its action restricts nothing, or the
     * content has no known author.
     *
     * @param list<Report> $open the reports open on the target, which tell whether a member prompted the decision and
     *                           whether automated means detected the content
     *
     * @throws InvalidModeration when the grounds are missing, their reason is not in the catalogue, or a field is refused
     */
    public function draft(Decision $decision, ?Grounds $grounds, array $open): ?StatementOfReasons
    {
        if (!$decision->action->restricts() || !$decision->author instanceof MemberId) {
            return null;
        }

        if (!$grounds instanceof Grounds) {
            throw InvalidModeration::because('A decision that restricts the author states its grounds: a reason and the facts it rests on.');
        }

        if (!$this->reasons->has($grounds->reason)) {
            throw InvalidModeration::because(\sprintf('"%s" is not a reason of the catalogue.', $grounds->reason));
        }

        $reference = trim($grounds->reference ?? '');

        return new StatementOfReasons(
            Identifiers::generate(),
            $decision->id,
            $decision->author,
            $decision->target,
            $decision->action,
            $grounds->reason,
            $this->grounds->ground($grounds->reason),
            '' === $reference ? $this->grounds->reference($grounds->reason) : $reference,
            $grounds->explanation,
            array_any($open, static fn (Report $report): bool => ReportSource::Member === $report->source),
            array_any($open, static fn (Report $report): bool => ReportSource::WatchedWords === $report->source),
            $decision->decidedAt,
        );
    }

    /**
     * Keeps the statement, then tells the application it was issued.
     */
    public function issue(StatementOfReasons $statement): void
    {
        $this->statements->add($statement);
        $this->events?->dispatch(new StatementIssued($statement));
    }

    /**
     * The statement of the id, when it is addressed to the current member.
     *
     * @phpstan-impure
     */
    public function mine(string $id): ?StatementOfReasons
    {
        $member = $this->currentMember->member();

        if (!$member instanceof MemberId) {
            return null;
        }

        $statement = $this->statements->find($id);

        return $statement?->recipient->value === $member->value ? $statement : null;
    }

    /**
     * The statements addressed to the current member, the latest first; none for a visitor.
     *
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<StatementOfReasons>
     *
     * @phpstan-impure
     */
    public function allMine(int $limit, int $offset = 0): array
    {
        $member = $this->currentMember->member();

        return $member instanceof MemberId ? $this->statements->addressedTo($member, $limit, $offset) : [];
    }

    /**
     * @phpstan-impure
     */
    public function countMine(): int
    {
        $member = $this->currentMember->member();

        return $member instanceof MemberId ? $this->statements->countAddressedTo($member) : 0;
    }

    /**
     * The statement that came with the decision, for the moderation.
     *
     * @phpstan-impure
     */
    public function forDecision(string $decisionId): ?StatementOfReasons
    {
        return $this->statements->forDecision($decisionId);
    }

    public function redress(): Redress
    {
        return $this->redress;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\Ground;

/**
 * The reasoned notice the author of a content receives when a decision restricts it — hides it, removes it, or warns
 * them: what was decided and on what, the reason, whether it rests on the terms of the site or on the law and which
 * provision, the facts the moderator relied on, whether a report from a member prompted it or the moderation acted on
 * its own, and whether the content was detected by automated means. It never names the moderator nor the reporter.
 *
 * Its fields follow those article 17 of the European Digital Services Act asks of a statement of reasons; the ways to
 * contest it are those the site configures when the statement is shown.
 */
final readonly class StatementOfReasons
{
    public const int MAX_EXPLANATION_LENGTH = 2000;

    public const int MAX_REFERENCE_LENGTH = 255;

    public string $explanation;

    public ?string $reference;

    public function __construct(
        public string $id,
        public string $decisionId,
        public MemberId $recipient,
        public TargetReference $target,
        public DecisionAction $action,
        public string $reason,
        public Ground $ground,
        ?string $reference,
        ?string $explanation,
        public bool $prompted,
        public bool $automatedDetection,
        public DateTimeImmutable $issuedAt,
    ) {
        Identifiers::check($id, 'A statement of reasons');
        Identifiers::check($decisionId, 'A decision');
        Identifiers::code($reason, 'a reason');

        if (!$action->restricts()) {
            throw InvalidModeration::because(\sprintf('The action "%s" restricts nothing: no statement of reasons follows it.', $action->value));
        }

        $this->reference = Identifiers::text($reference, self::MAX_REFERENCE_LENGTH, 'A reference');
        $this->explanation = Identifiers::text($explanation, self::MAX_EXPLANATION_LENGTH, 'An explanation')
            ?? throw InvalidModeration::because('A statement of reasons explains the facts the decision rests on.');

        if (Ground::Illegal === $ground && null === $this->reference) {
            throw InvalidModeration::because('A restriction for illegal content names the law it rests on.');
        }
    }
}

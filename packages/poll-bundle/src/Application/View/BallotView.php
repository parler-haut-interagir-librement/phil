<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\View;

/**
 * What one viewer sees of the form of a poll: the question, the kind of ballot and its bounds, the options with what
 * the viewer's ballot gave them, and what the viewer may do — cast a ballot, change it, or neither. A template draws it
 * as it is; it asks nothing more.
 */
final readonly class BallotView
{
    /**
     * @param 'single'|'multiple'|'ranked' $kind
     * @param list<BallotChoice>           $choices
     */
    public function __construct(
        public string $poll,
        public string $question,
        public string $kind,
        public array $choices,
        public int $minChoices,
        public int $maxChoices,
        public bool $abstention,
        public bool $anonymous,
        public int $seats,
        public bool $open,
        public bool $voted,
        public bool $mayCast,
        public bool $mayChange,
    ) {
    }

    /**
     * Whether the form is drawn with its button: the viewer may cast a ballot, or change theirs.
     */
    public function isEditable(): bool
    {
        return $this->mayCast || $this->mayChange;
    }
}

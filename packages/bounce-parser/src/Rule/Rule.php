<?php

declare(strict_types=1);

namespace Phil\BounceParser\Rule;

use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\Exception\InvalidRule;

/**
 * A rule the application declares for the messages no standard describes: a pattern on one field of the message, the
 * kind of bounce it means and the action it leads to.
 */
final readonly class Rule
{
    private function __construct(
        public string $name,
        public Field $field,
        public string $pattern,
        public BounceKind $kind,
        public BounceAction $action,
        public RuleScope $scope,
        public ?string $header,
    ) {
    }

    /**
     * A rule on a regular expression of PCRE, delimiters and flags included, `/mailbox (is )?full/i`.
     *
     * @param BounceAction|null $action the action of the kind when none is given
     * @param string|null       $header the field, for `Field::Header`
     *
     * @throws InvalidRule when the pattern is not a valid regular expression, or a rule on a header names none
     */
    public static function matching(
        string $name,
        Field $field,
        string $pattern,
        BounceKind $kind,
        ?BounceAction $action = null,
        RuleScope $scope = RuleScope::Unrecognised,
        ?string $header = null,
    ): self {
        if (false === @preg_match($pattern, '')) {
            throw InvalidRule::pattern($name);
        }

        if (Field::Header === $field && null === $header) {
            throw InvalidRule::header($name);
        }

        return new self($name, $field, $pattern, $kind, $action ?? BounceAction::for($kind), $scope, $header);
    }

    /**
     * A rule on a text the field contains, without regard to case.
     *
     * @throws InvalidRule when the text is empty
     */
    public static function containing(
        string $name,
        Field $field,
        string $text,
        BounceKind $kind,
        ?BounceAction $action = null,
        RuleScope $scope = RuleScope::Unrecognised,
        ?string $header = null,
    ): self {
        if ('' === $text) {
            throw InvalidRule::emptyNeedle($name);
        }

        return self::matching($name, $field, '/'.preg_quote($text, '/').'/iu', $kind, $action, $scope, $header);
    }

    /**
     * Whether the text matches. A pattern that fails on a text — too much backtracking, broken UTF-8 — does not match.
     */
    public function matches(string $text): bool
    {
        return 1 === @preg_match($this->pattern, $text);
    }
}

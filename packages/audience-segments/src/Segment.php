<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\Rules\Rule;

/**
 * A named rule of phil/rules on the facts of an audience member: who holds a ticket, attended a date, bought a
 * product, supports a tier or carries a tag. Its members are computed when asked, so they change with the facts; a
 * `FrozenList` keeps them as they were at a date.
 *
 * A segment refuses the condition types that guess a trait or score a behaviour, and those that read whether a
 * message was opened or clicked: their names hold one of the words of `FORBIDDEN_WORDS`.
 */
final readonly class Segment
{
    /** The fact every evaluation receives: the opaque identifier of the member. */
    public const string MEMBER = 'member';

    public const array FORBIDDEN_WORDS = [
        'open', 'opens', 'opened', 'opening', 'click', 'clicks', 'clicked', 'score', 'scores', 'scored', 'scoring',
        'predicted', 'prediction', 'inferred', 'propensity', 'lookalike', 'affinity',
    ];

    /**
     * @throws InvalidSegment when the identifier or the name is out of form, or the rule uses a forbidden type
     */
    public function __construct(
        public string $id,
        public string $name,
        public Rule $rule,
    ) {
        Identifier::check('segment', $id);

        if ('' === $name || \strlen($name) > 200 || 1 !== preg_match('/^[^\x00-\x1F\x7F]+$/Du', $name)) {
            throw InvalidSegment::name();
        }

        foreach ($rule->typeNames() as $type) {
            foreach (explode('_', strtr($type, '.-', '__')) as $word) {
                if (\in_array($word, self::FORBIDDEN_WORDS, true)) {
                    throw InvalidSegment::forbiddenCondition($type);
                }
            }
        }
    }
}

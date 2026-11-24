<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * The published criteria under which a review is withdrawn, in the order the page of the rules lists them. No review —
 * a negative one least of all — is withdrawn outside them. Their labels and explanations are translated by code, in
 * the domain `phil_reviews`, under `criterion.<code>` and `criterion.<code>.help`.
 */
final readonly class RemovalCriteria
{
    public const array DEFAULTS = ['spam', 'off-topic', 'hate-or-harassment', 'personal-information', 'conflict-of-interest', 'not-a-participant', 'illegal'];

    /** @var non-empty-list<string> */
    private array $codes;

    /**
     * @param list<string> $codes
     *
     * @throws InvalidReview when a code is malformed or none is given
     */
    public function __construct(array $codes = self::DEFAULTS)
    {
        $codes = array_values(array_unique(array_map(static fn (string $code): string => Texts::code($code, 'a removal criterion'), $codes)));

        if ([] === $codes) {
            throw InvalidReview::because('The reviews publish at least one removal criterion.');
        }

        $this->codes = $codes;
    }

    /**
     * @return non-empty-list<string>
     */
    public function all(): array
    {
        return $this->codes;
    }

    public function has(string $code): bool
    {
        return \in_array($code, $this->codes, true);
    }

    /**
     * The criterion a public reason of the moderation names: the code alone, or the code followed by ":" or a space and
     * an explanation; `null` when it names none.
     */
    public function fromReason(?string $reason): ?string
    {
        if (null === $reason) {
            return null;
        }

        if (1 !== preg_match('/^\s*([^\s:]+)/', $reason, $match)) {
            return null;
        }

        $code = strtolower($match[1]);

        return $this->has($code) ? $code : null;
    }
}

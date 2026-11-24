<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * What a member writes in a review: the overall score, the score of each criterion they rated, an optional title and
 * text, and up to three superlatives — the positive labels they chose. The scale and the codes a subject type accepts
 * are checked by its `SubjectType`; this checks what holds for every type.
 */
final readonly class ReviewContent
{
    /** The largest scale a type can have. */
    public const int MAX_SCALE = 10;

    public const int MAX_TITLE_LENGTH = 150;

    /** The longest text any configuration accepts; each type sets its own, shorter by default. */
    public const int MAX_BODY_LENGTH = 20_000;

    public const int MAX_SUPERLATIVES = 3;

    public const int MAX_CRITERIA = 20;

    public ?string $title;

    public ?string $body;

    /** @var array<string, int<1, 10>> */
    public array $criteria;

    /** @var list<string> */
    public array $superlatives;

    /** @var int<1, 10> */
    public int $overall;

    /**
     * @param array<array-key, mixed> $criteria     the score of each rated criterion, by code
     * @param list<string>            $superlatives
     *
     * @throws InvalidReview when a score is out of 1 to 10, a code is malformed, a text too long, or there are more than
     *                       three superlatives
     */
    public function __construct(
        int $overall,
        array $criteria = [],
        ?string $title = null,
        ?string $body = null,
        array $superlatives = [],
    ) {
        $this->overall = $this->score($overall, 'The overall score');

        if (\count($criteria) > self::MAX_CRITERIA) {
            throw InvalidReview::because(\sprintf('A review rates %d criteria at most.', self::MAX_CRITERIA));
        }

        $scores = [];

        foreach ($criteria as $code => $score) {
            if (!\is_string($code) || !\is_int($score)) {
                throw InvalidReview::because('The scores of the criteria are integers by code.');
            }

            $scores[Texts::code($code, 'a criterion')] = $this->score($score, \sprintf('The score of "%s"', $code));
        }

        ksort($scores);
        $this->criteria = $scores;
        $this->title = Texts::optional($title, self::MAX_TITLE_LENGTH, 'A title');
        $this->body = Texts::optional($body, self::MAX_BODY_LENGTH, 'A review');

        $labels = array_values(array_unique(array_map(static fn (string $label): string => Texts::code($label, 'a superlative'), $superlatives)));

        if (\count($labels) > self::MAX_SUPERLATIVES) {
            throw InvalidReview::because(\sprintf('A review gives %d superlatives at most.', self::MAX_SUPERLATIVES));
        }

        $this->superlatives = $labels;
    }

    /**
     * @return int<1, 10>
     */
    private function score(int $score, string $what): int
    {
        if ($score < 1 || $score > self::MAX_SCALE) {
            throw InvalidReview::because(\sprintf('%s is from 1 to %d.', $what, self::MAX_SCALE));
        }

        return $score;
    }
}

<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * What the configuration says of one type of subject: its scale, the criteria a review rates, the positive labels it
 * offers as superlatives and how many a review gives, whether only its participants review it and within how many
 * days, the prior of its weighted average, and how long a text is.
 */
final readonly class SubjectType
{
    public const int DEFAULT_SCALE = 5;

    public const int DEFAULT_WINDOW = 30;

    public const int DEFAULT_BODY_LENGTH = 5000;

    public const int DEFAULT_PRIOR_COUNT = 5;

    public const int DEFAULT_MINIMUM = 3;

    /** @var list<string> */
    public array $criteria;

    /** @var list<string> */
    public array $superlatives;

    public BayesianPrior $prior;

    /**
     * @param list<string> $criteria     the codes of the criteria a review rates, in the order the form shows them
     * @param list<string> $superlatives the codes of the labels offered, in their order
     * @param ?int         $window       how many days after their participation a participant reviews; `null` for no limit
     * @param ?float       $priorMean    the neutral value of the weighted average; the middle of the scale when null
     *
     * @throws InvalidReview when the name, a code, the scale or a count is refused
     */
    public function __construct(
        public string $name,
        public int $scale = self::DEFAULT_SCALE,
        array $criteria = [],
        array $superlatives = [],
        public int $maxSuperlatives = ReviewContent::MAX_SUPERLATIVES,
        public bool $participantsOnly = false,
        public ?int $window = self::DEFAULT_WINDOW,
        int $priorCount = self::DEFAULT_PRIOR_COUNT,
        ?float $priorMean = null,
        int $minimum = self::DEFAULT_MINIMUM,
        public int $bodyLength = self::DEFAULT_BODY_LENGTH,
        public int $superlativesShown = ReviewContent::MAX_SUPERLATIVES,
    ) {
        if (1 !== preg_match(SubjectRef::TYPE, $name)) {
            throw InvalidReview::because(\sprintf('"%s" is not a subject type.', $name));
        }

        if ($scale < 2 || $scale > ReviewContent::MAX_SCALE) {
            throw InvalidReview::because(\sprintf('The scale of "%s" is from 2 to %d.', $name, ReviewContent::MAX_SCALE));
        }

        if ($maxSuperlatives < 0 || $maxSuperlatives > ReviewContent::MAX_SUPERLATIVES || $superlativesShown < 0) {
            throw InvalidReview::because(\sprintf('A review of "%s" gives from 0 to %d superlatives.', $name, ReviewContent::MAX_SUPERLATIVES));
        }

        if ($bodyLength < 1 || $bodyLength > ReviewContent::MAX_BODY_LENGTH) {
            throw InvalidReview::because(\sprintf('The text of a review of "%s" holds from 1 to %d characters.', $name, ReviewContent::MAX_BODY_LENGTH));
        }

        if (null !== $window && $window < 1) {
            throw InvalidReview::because(\sprintf('The window of "%s" is one day or more, or none.', $name));
        }

        $this->criteria = $this->codes($criteria, 'a criterion', ReviewContent::MAX_CRITERIA);
        $this->superlatives = $this->codes($superlatives, 'a superlative', 64);

        $mean = $priorMean ?? (1 + $scale) / 2;

        if ($mean > $scale) {
            throw InvalidReview::because(\sprintf('The neutral value of "%s" is within its scale.', $name));
        }

        $this->prior = new BayesianPrior($priorCount, $mean, $minimum);
    }

    /**
     * The content checked against the type: the scale, the criteria it rates, the superlatives it offers and how many,
     * the length of the text.
     *
     * @throws InvalidReview when a score is above the scale, a criterion or a superlative is not the type's, or the text is too long
     */
    public function check(ReviewContent $content): ReviewContent
    {
        foreach ([$content->overall, ...array_values($content->criteria)] as $score) {
            if ($score > $this->scale) {
                throw InvalidReview::because(\sprintf('The scores of a review of "%s" are from 1 to %d.', $this->name, $this->scale));
            }
        }

        $unknown = array_diff(array_keys($content->criteria), $this->criteria);

        if ([] !== $unknown) {
            throw InvalidReview::because(\sprintf('"%s" is not a criterion of "%s".', implode('", "', $unknown), $this->name));
        }

        $unknown = array_diff($content->superlatives, $this->superlatives);

        if ([] !== $unknown) {
            throw InvalidReview::because(\sprintf('"%s" is not a superlative of "%s".', implode('", "', $unknown), $this->name));
        }

        if (\count($content->superlatives) > $this->maxSuperlatives) {
            throw InvalidReview::because(\sprintf('A review of "%s" gives %d superlatives at most.', $this->name, $this->maxSuperlatives));
        }

        if (null !== $content->body && mb_strlen($content->body) > $this->bodyLength) {
            throw InvalidReview::because(\sprintf('A review of "%s" holds %d characters at most.', $this->name, $this->bodyLength));
        }

        return $content;
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private function codes(array $codes, string $what, int $maximum): array
    {
        $codes = array_values(array_unique(array_map(static fn (string $code): string => Texts::code($code, $what), $codes)));

        if (\count($codes) > $maximum) {
            throw InvalidReview::because(\sprintf('A subject type has %d of %s at most.', $maximum, $what));
        }

        return $codes;
    }
}

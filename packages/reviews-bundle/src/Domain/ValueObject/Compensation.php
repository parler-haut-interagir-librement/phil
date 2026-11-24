<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * Whether the author received something in exchange for the review — a free product, a discount, a payment, a free
 * seat — and what, in their words. A review given against a compensation always shows it.
 */
final readonly class Compensation
{
    public const int MAX_NOTE_LENGTH = 200;

    public ?string $note;

    /**
     * @throws InvalidReview when a note is given without a compensation, or is too long
     */
    private function __construct(
        public bool $received,
        ?string $note,
    ) {
        $note = Texts::optional($note, self::MAX_NOTE_LENGTH, 'What was received');

        if (!$received && null !== $note) {
            throw InvalidReview::because('What was received is told only of a review given against a compensation.');
        }

        $this->note = $note;
    }

    public static function none(): self
    {
        return new self(false, null);
    }

    /**
     * @throws InvalidReview when the note is too long
     */
    public static function received(?string $note = null): self
    {
        return new self(true, $note);
    }

    /**
     * @throws InvalidReview when a note is given without a compensation, or is too long
     */
    public static function of(bool $received, ?string $note): self
    {
        return new self($received, $note);
    }

    /**
     * The compensation without the author's own words, kept by an anonymised review.
     */
    public function withoutNote(): self
    {
        return new self($this->received, null);
    }
}

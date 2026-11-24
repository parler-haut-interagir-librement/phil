<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;

/**
 * The public response of the merchant or the organiser to a review: one per review, shown under it, with who wrote it
 * and when.
 */
final readonly class Response
{
    public const int MAX_LENGTH = 5000;

    public string $text;

    /**
     * @throws InvalidReview when the text is empty or too long
     */
    public function __construct(
        string $text,
        public ?MemberId $responder,
        public DateTimeImmutable $at,
    ) {
        $this->text = Texts::required($text, self::MAX_LENGTH, 'A response');
    }
}

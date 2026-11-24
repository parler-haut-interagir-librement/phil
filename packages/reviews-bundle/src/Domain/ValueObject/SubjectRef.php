<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Texts;
use Stringable;

/**
 * What is reviewed: a type, which the configuration declares — `product`, `game`, `place` —, and the identifier the
 * application gives the subject in it. The bundle holds no subject; it only names them.
 */
final readonly class SubjectRef implements Stringable
{
    public const string TYPE = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    public const int MAX_ID_LENGTH = 190;

    private function __construct(
        public string $type,
        public string $id,
    ) {
    }

    /**
     * @throws InvalidReview when the type or the identifier is malformed
     */
    public static function of(string $type, string $id): self
    {
        if (1 !== preg_match(self::TYPE, $type)) {
            throw InvalidReview::because(\sprintf('"%s" is not a subject type: a lowercase letter, then up to 63 lowercase letters, digits, ".", "-" and "_".', $type));
        }

        return new self($type, Texts::identifier($id, self::MAX_ID_LENGTH, 'A subject identifier'));
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    public function __toString(): string
    {
        return $this->type.':'.$this->id;
    }
}

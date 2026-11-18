<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;

/**
 * A learner, named by the stable identifier of their account, never by their e-mail address: the identifier travels in
 * the xAPI statements and the launch URLs of cmi5, where a personal value would leak.
 */
final readonly class LearnerId
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCourse when the value is not an identifier, an e-mail address included
     */
    public static function of(string $value): self
    {
        return new self(Identifiers::check('A learner', $value, self::MAX_LENGTH));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

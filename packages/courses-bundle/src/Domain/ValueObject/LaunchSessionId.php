<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Symfony\Component\Uid\Uuid;

/**
 * The identifier of a session: a UUID the bundle draws, or an identifier of the same shape a store gives back.
 */
final readonly class LaunchSessionId
{
    public const int MAX_LENGTH = 36;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCourse when the value is not an identifier
     */
    public static function of(string $value): self
    {
        return new self(Identifiers::check('A session', $value, self::MAX_LENGTH));
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

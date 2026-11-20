<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\ValueObject;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * Whom a task is charged to: the application's opaque reference to an account, a tenant or a project, 1 to 255 bytes —
 * the subject of its quotas too.
 */
final readonly class Subject
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidAiValue
     */
    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidAiValue::because(\sprintf('A subject is named by 1 to %d bytes.', self::MAX_LENGTH));
        }

        return new self($value);
    }
}

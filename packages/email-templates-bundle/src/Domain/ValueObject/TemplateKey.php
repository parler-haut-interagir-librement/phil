<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Stringable;

/**
 * The name of a template, shared by every site and locale: 1 to 64 lowercase ASCII letters, digits, "_", "." or "-",
 * starting with a letter, as `order.confirmation` or `ticket`. It compares byte for byte.
 */
final readonly class TemplateKey implements Stringable
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidTemplate when the text is not a key
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidTemplate::because(\sprintf('A template key is 1 to %d lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', self::MAX_LENGTH, self::shown($value)));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * The start of a refused text, short enough for a message.
     */
    public static function shown(string $value): string
    {
        return mb_strlen($value) > 80 ? mb_substr($value, 0, 80).'…' : $value;
    }
}

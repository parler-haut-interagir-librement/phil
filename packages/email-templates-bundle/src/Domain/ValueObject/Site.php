<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Stringable;

/**
 * The site a template belongs to, or the shared scope every site reads when it has no template of its own. A site is
 * 1 to 64 ASCII letters, digits, "_", ".", ":" or "-", starting with a letter or a digit — the identifier of a tenant,
 * of a domain or of a brand; the shared scope is the empty text. It compares byte for byte.
 */
final readonly class Site implements Stringable
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D';

    private function __construct(
        public string $value,
    ) {
    }

    public static function shared(): self
    {
        return new self('');
    }

    /**
     * @throws InvalidTemplate when the text is neither a site nor empty
     */
    public static function fromString(string $value): self
    {
        if ('' !== $value && 1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidTemplate::because(\sprintf('A site is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter or a digit; "%s" is not.', self::MAX_LENGTH, TemplateKey::shown($value)));
        }

        return new self($value);
    }

    public function isShared(): bool
    {
        return '' === $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

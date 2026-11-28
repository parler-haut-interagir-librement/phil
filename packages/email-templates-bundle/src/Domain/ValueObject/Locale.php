<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Stringable;

/**
 * The locale of a template: a language of two or three lowercase letters, optionally followed by a region or a script,
 * as `en`, `fr_CA` or `zh_Hant`. A hyphen is read as an underscore, so `fr-CA` is `fr_CA`.
 */
final readonly class Locale implements Stringable
{
    public const int MAX_LENGTH = 16;

    private const string PATTERN = '/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})?$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidTemplate when the text is not a locale
     */
    public static function fromString(string $value): self
    {
        $locale = str_replace('-', '_', $value);

        if (1 !== preg_match(self::PATTERN, $locale)) {
            throw InvalidTemplate::because(\sprintf('A locale is a language of two or three lowercase letters, optionally followed by "_" and a region or a script, as "fr_CA"; "%s" is not.', TemplateKey::shown($value)));
        }

        return new self($locale);
    }

    /**
     * The language alone: `fr` for `fr_CA`.
     */
    public function language(): self
    {
        $at = strpos($this->value, '_');

        return false === $at ? $this : new self(substr($this->value, 0, $at));
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

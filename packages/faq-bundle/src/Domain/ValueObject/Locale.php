<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\ValueObject;

use Override;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Stringable;

/**
 * A language, and maybe a region, in the spelling of Symfony's translator: `fr` or `fr_CA`; `fr-CA` is read as
 * `fr_CA`.
 */
final readonly class Locale implements Stringable
{
    private const string PATTERN = '/^(?<language>[a-z]{2,3})(?:[_-](?<region>[A-Z]{2}|\d{3}))?$/D';

    private function __construct(
        public string $value,
        public string $language,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidFaq when it is not a language with an optional region
     */
    public static function fromString(string $locale): self
    {
        if (1 !== preg_match(self::PATTERN, $locale, $parts)) {
            throw InvalidFaq::because('A locale is a language, and maybe a region: "fr" or "fr_CA".');
        }

        $region = $parts['region'] ?? '';

        return new self('' === $region ? $parts['language'] : $parts['language'].'_'.$region, $parts['language']);
    }

    /**
     * The locales a text is looked for in, the first found winning: this one, then its language alone.
     *
     * @return non-empty-list<string>
     */
    public function chain(): array
    {
        return $this->value === $this->language ? [$this->value] : [$this->value, $this->language];
    }

    /**
     * The BCP 47 tag of the locale, `fr-CA`, as the HTML `lang` attribute and JSON-LD write it.
     */
    public function tag(): string
    {
        return str_replace('_', '-', $this->value);
    }
}

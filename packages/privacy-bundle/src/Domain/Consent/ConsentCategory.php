<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Consent;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * One kind of cookie or tracker a site declares: what it is called and what it sets.
 *
 * A **necessary** category is the one kind a person is not asked about: the site does not work without it.
 */
final readonly class ConsentCategory
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_-]{0,31}$/D';

    /**
     * @param array<string, string> $labels       by locale
     * @param array<string, string> $descriptions by locale
     * @param list<string>          $cookies      what the category sets, named for the person who reads the list
     */
    public function __construct(
        public string $name,
        public bool $necessary = false,
        public array $labels = [],
        public array $descriptions = [],
        public array $cookies = [],
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a consent category: lowercase letters, digits, "_" and "-", at most 32 characters.', $name));
        }
    }

    public function label(string $locale): string
    {
        return $this->labels[$locale] ?? $this->name;
    }

    public function description(string $locale): string
    {
        return $this->descriptions[$locale] ?? '';
    }
}

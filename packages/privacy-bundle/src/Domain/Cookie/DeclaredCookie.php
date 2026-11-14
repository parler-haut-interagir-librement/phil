<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Cookie;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * One line of the cookie inventory: what a site, or a service it embeds, writes in the visitor's browser, under which
 * consent category, why and for how long.
 *
 * A name that ends with `*` covers a family (`_pk_id.*` covers `_pk_id.1.1fff`): a prefix, never a pattern, so that
 * matching stays one comparison. The duration is an ISO 8601 duration or `session`, until the browser closes.
 *
 * A cookie without a category is the one the consent itself writes: it keeps the choice, and no choice can refuse it.
 */
final readonly class DeclaredCookie
{
    public const string NAME_PATTERN = '/^[!#$%&\'+.0-9A-Z^_`a-z|~-]{1,128}\*?$/D';

    public const string DURATION_PATTERN = '/^(session|P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?)$/D';

    public const int MAX_TEXT = 500;

    private string $fallback;

    /**
     * @param array<string, string> $purposes by locale
     * @param string|null           $provider who writes it, when it is not the site itself
     */
    public function __construct(
        public string $name,
        public ?string $category,
        public array $purposes,
        public string $duration,
        public ?string $provider = null,
        public CookieStorage $storage = CookieStorage::Cookie,
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name) || '*' === $name) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a cookie name: the characters of a cookie name, at most 128, maybe ending with "*" for a family.', $name));
        }

        if ('' === $category) {
            throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" names an empty category.', $name));
        }

        if (1 !== preg_match(self::DURATION_PATTERN, $duration)) {
            throw InvalidPrivacyValue::because(\sprintf('The duration of the cookie "%s" is "session" or an ISO 8601 duration such as "P13M".', $name));
        }

        if ([] === $purposes) {
            throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" states no purpose: the person who reads the policy is owed one.', $name));
        }

        foreach ($purposes as $locale => $purpose) {
            $this->text(\sprintf('The purpose of the cookie "%s" in "%s"', $name, $locale), $purpose);
        }

        if (null !== $provider) {
            $this->text(\sprintf('The provider of the cookie "%s"', $name), $provider);
        }

        $this->fallback = $purposes['en'] ?? reset($purposes);
    }

    public function isFamily(): bool
    {
        return str_ends_with($this->name, '*');
    }

    /**
     * Whether a name the browser received is this cookie, or one of its family.
     */
    public function matches(string $name): bool
    {
        return $this->isFamily() ? str_starts_with($name, substr($this->name, 0, -1)) : $name === $this->name;
    }

    public function purpose(string $locale): string
    {
        return $this->purposes[$locale] ?? $this->fallback;
    }

    public function isSession(): bool
    {
        return 'session' === $this->duration;
    }

    private function text(string $what, string $value): void
    {
        if ('' === trim($value) || mb_strlen($value) > self::MAX_TEXT || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidPrivacyValue::because(\sprintf('%s is one line of at most %d characters.', $what, self::MAX_TEXT));
        }
    }
}

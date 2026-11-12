<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * A category of cookies and similar trackers a visitor consents to, as the privacy policy lists it: its key, cited by
 * the body as `${consent.<key>}`, whether it is necessary — set without consent —, its name and description in each
 * locale, and the names of its cookies, a `*` standing for any end of a name.
 */
final readonly class ConsentCategory
{
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';

    public const string COOKIE_PATTERN = '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]{1,128}$/D';

    public const int MAX_COOKIES = 100;

    public const int MAX_LABEL_BYTES = 200;

    public const int MAX_DESCRIPTION_BYTES = 2000;

    /**
     * @var non-empty-array<string, non-empty-string>
     */
    public array $labels;

    /**
     * @var array<string, non-empty-string>
     */
    public array $descriptions;

    /**
     * @var list<string>
     */
    public array $cookies;

    /**
     * @param array<array-key, mixed> $labels       by locale
     * @param array<array-key, mixed> $descriptions by locale
     * @param array<array-key, mixed> $cookies
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(
        public string $key,
        public bool $necessary,
        array $labels,
        array $descriptions = [],
        array $cookies = [],
    ) {
        if (1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw InvalidLegalNotice::because('consent_key', \sprintf('%s is not a consent category key: a lowercase letter, then lowercase letters, digits and "_", 64 characters at most.', InvalidLegalNotice::quote($key)));
        }

        $labels = Locales::texts($labels, self::MAX_LABEL_BYTES, 'consent category name');
        $this->labels = [] !== $labels ? $labels : throw InvalidLegalNotice::because('consent_label', \sprintf('The consent category %s has a name in one locale at least.', $key));
        $this->descriptions = Locales::texts($descriptions, self::MAX_DESCRIPTION_BYTES, 'consent category description');
        $this->cookies = $this->cookies($key, $cookies);
    }

    public function label(string $locale, string $fallback): string
    {
        return Locales::pick($this->labels, $locale, $fallback);
    }

    public function description(string $locale, string $fallback): ?string
    {
        return [] === $this->descriptions ? null : Locales::pick($this->descriptions, $locale, $fallback);
    }

    /**
     * @param array<array-key, mixed> $cookies
     *
     * @return list<string>
     */
    private function cookies(string $key, array $cookies): array
    {
        if (\count($cookies) > self::MAX_COOKIES) {
            throw InvalidLegalNotice::because('too_many_cookies', \sprintf('The consent category %s names 100 cookies at most.', $key));
        }

        $kept = [];

        foreach ($cookies as $cookie) {
            if (!\is_string($cookie) || 1 !== preg_match(self::COOKIE_PATTERN, $cookie)) {
                throw InvalidLegalNotice::because('cookie_name', \sprintf('The consent category %s names a cookie that is not a cookie name.', $key));
            }

            $kept[] = $cookie;
        }

        return $kept;
    }
}

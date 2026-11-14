<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Cookie;

use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * Everything a site writes in the visitor's browser, declared once: the banner, the page of the choices and the cookie
 * policy are drawn from it, and a functional test fails on a response that sets a cookie it does not declare.
 *
 * Each category it names must be a category of the consent policy, so that nothing is declared under a choice nobody
 * is asked.
 */
final readonly class CookieInventory
{
    /**
     * @var list<DeclaredCookie>
     */
    public array $cookies;

    /**
     * @var array<string, list<DeclaredCookie>> by category, the consent's own under ""
     */
    private array $byCategory;

    /**
     * @param iterable<DeclaredCookie> $cookies
     */
    public function __construct(iterable $cookies, ?ConsentPolicy $policy = null)
    {
        $lines = [];
        $byPlace = [];

        foreach ($cookies as $cookie) {
            if (isset($byPlace[$cookie->storage->value][$cookie->name])) {
                throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" is declared twice.', $cookie->name));
            }

            if ($policy instanceof ConsentPolicy && null !== $cookie->category && !isset($policy->categories[$cookie->category])) {
                throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" is declared under "%s", which is not a consent category.', $cookie->name, $cookie->category));
            }

            // A name is unique within its storage only: the same one may live in a cookie and in local storage.
            $byPlace[$cookie->storage->value][$cookie->name] = $cookie;
            $lines[] = $cookie;
        }

        $this->cookies = $lines;
        $byCategory = [];

        foreach ($this->cookies as $cookie) {
            $byCategory[$cookie->category ?? ''][] = $cookie;
        }

        $this->byCategory = $byCategory;
    }

    /**
     * The inventory written as a map of categories to maps of names, as in `config/privacy/cookies.yaml`:
     *
     * ```yaml
     * analytics:
     *     _pk_id.*: { purpose: { en: Tells visits apart, fr: Distingue les visites }, duration: P13M, provider: Matomo }
     * ```
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, ?ConsentPolicy $policy = null): self
    {
        $cookies = [];

        foreach ($data as $category => $names) {
            if (!\is_string($category) || !\is_array($names)) {
                throw InvalidPrivacyValue::because('The cookie inventory maps each consent category to its cookies.');
            }

            foreach ($names as $name => $line) {
                $cookies[] = self::line((string) $name, $category, $line);
            }
        }

        return new self($cookies, $policy);
    }

    /**
     * The same inventory with one more line, the one the bundle writes itself.
     */
    public function with(DeclaredCookie $cookie): self
    {
        return new self([$cookie, ...$this->cookies]);
    }

    public function declares(string $name, CookieStorage $storage = CookieStorage::Cookie): bool
    {
        return array_any($this->cookies, static fn (DeclaredCookie $cookie): bool => $cookie->storage === $storage && $cookie->matches($name));
    }

    /**
     * The names of the list that the inventory does not declare as cookies, in the order given, each once.
     *
     * @param iterable<string> $names
     *
     * @return list<string>
     */
    public function undeclared(iterable $names): array
    {
        $undeclared = [];

        foreach ($names as $name) {
            if (!$this->declares($name)) {
                $undeclared[$name] = $name;
            }
        }

        return array_values($undeclared);
    }

    /**
     * @return list<DeclaredCookie> the lines of one category; `null`, those of the consent itself
     */
    public function ofCategory(?string $category): array
    {
        return $this->byCategory[$category ?? ''] ?? [];
    }

    /**
     * @return list<string> the names of one category, for a short list
     */
    public function namesOf(?string $category): array
    {
        return array_map(static fn (DeclaredCookie $cookie): string => $cookie->name, $this->ofCategory($category));
    }

    /**
     * The inventory by group: the consent's own cookies, then each category of the policy in the order of its
     * declaration, a category without a cookie included, since the person is asked about it all the same.
     *
     * @return list<CookieGroup>
     */
    public function groups(ConsentPolicy $policy): array
    {
        $own = $this->ofCategory(null);
        $groups = [] === $own ? [] : [new CookieGroup(null, $own)];

        foreach ($policy->categories as $name => $category) {
            $groups[] = new CookieGroup($category, $this->ofCategory($name));
        }

        return $groups;
    }

    private static function line(string $name, string $category, mixed $line): DeclaredCookie
    {
        if (!\is_array($line)) {
            throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" is a map with "purpose" and "duration".', $name));
        }

        $unknown = array_diff(array_keys($line), ['purpose', 'duration', 'provider', 'storage']);

        if ([] !== $unknown) {
            throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" has an unknown key "%s": purpose, duration, provider and storage are known.', $name, (string) reset($unknown)));
        }

        $purpose = $line['purpose'] ?? null;
        $purposes = \is_string($purpose) ? ['en' => $purpose] : $purpose;
        $duration = $line['duration'] ?? null;
        $provider = $line['provider'] ?? null;
        $place = $line['storage'] ?? 'cookie';
        $storage = \is_string($place) ? CookieStorage::tryFrom($place) : null;

        if (!\is_array($purposes) || !\is_string($duration) || (null !== $provider && !\is_string($provider)) || !$storage instanceof CookieStorage) {
            throw InvalidPrivacyValue::because(\sprintf('The cookie "%s" needs a "purpose", text or texts by locale, and a "duration"; "provider" is text, "storage" is cookie, local_storage or session_storage.', $name));
        }

        $texts = [];

        foreach ($purposes as $locale => $text) {
            if (!\is_string($locale) || !\is_string($text)) {
                throw InvalidPrivacyValue::because(\sprintf('The purposes of the cookie "%s" are texts by locale.', $name));
            }

            $texts[$locale] = $text;
        }

        return new DeclaredCookie($name, $category, $texts, $duration, $provider, $storage);
    }
}

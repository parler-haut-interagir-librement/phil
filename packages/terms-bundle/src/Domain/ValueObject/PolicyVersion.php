<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;
use Phil\TermsBundle\Domain\TextFormat;

/**
 * One version of a policy, as it was published: its number in the policy, its text in each locale, the moment it was
 * published and the moment it takes effect, whether it changes the terms enough that every member must accept it
 * again, and the licence under which a member who accepts it publishes their contributions, when its policy names one.
 * Nothing of it changes once it is built: a correction is a new version.
 *
 * The first version of a policy is always material: there is nothing before it to have accepted.
 */
final readonly class PolicyVersion
{
    public const int MAX_TEXT_BYTES = 262144;

    public const int MAX_LOCALES = 32;

    public const int MAX_NUMBER = 999999;

    /**
     * @var non-empty-array<string, non-empty-string>
     */
    public array $texts;

    /**
     * @param array<array-key, mixed> $texts the text of each locale, by locale
     *
     * @throws InvalidTerms
     */
    public function __construct(
        public string $policy,
        public int $number,
        array $texts,
        public TextFormat $format,
        public DateTimeImmutable $publishedAt,
        public DateTimeImmutable $effectiveAt,
        public bool $material,
        public ?string $licence = null,
    ) {
        Identifiers::policy($policy);

        if (null !== $licence) {
            Identifiers::licence($licence);
        }

        if ($number < 1 || $number > self::MAX_NUMBER) {
            throw InvalidTerms::because('version_number', 'A version number goes from 1 to 999999.');
        }

        if (1 === $number && !$material) {
            throw InvalidTerms::because('first_version_material', 'The first version of a policy is material: there is nothing before it to have accepted.');
        }

        if ($effectiveAt < $publishedAt) {
            throw InvalidTerms::because('effective_before_publication', 'A version takes effect when it is published or later.');
        }

        $this->texts = $this->texts($texts);
    }

    /**
     * The text in a locale, else in the fallback locale, else in the first locale of the version.
     */
    public function text(string $locale, string $fallback): string
    {
        return $this->texts[$this->textLocale($locale, $fallback)];
    }

    /**
     * The locale whose text `text()` gives: the locale when the version has its text, else the fallback locale when it
     * has that one, else the first locale of the version.
     */
    public function textLocale(string $locale, string $fallback): string
    {
        if (isset($this->texts[$locale])) {
            return $locale;
        }

        return isset($this->texts[$fallback]) ? $fallback : array_key_first($this->texts);
    }

    /**
     * @return non-empty-list<string>
     */
    public function locales(): array
    {
        return array_keys($this->texts);
    }

    /**
     * Whether the version has taken effect at a moment.
     */
    public function isInForceAt(DateTimeInterface $at): bool
    {
        return $this->effectiveAt <= $at;
    }

    /**
     * @param array<array-key, mixed> $texts
     *
     * @return non-empty-array<string, non-empty-string>
     */
    private function texts(array $texts): array
    {
        if (\count($texts) > self::MAX_LOCALES) {
            throw InvalidTerms::because('too_many_locales', 'A version has the text of 32 locales at most.');
        }

        $kept = [];

        foreach ($texts as $locale => $text) {
            $locale = Identifiers::locale((string) $locale);

            if (!\is_string($text) || '' === trim($text)) {
                throw InvalidTerms::because('empty_text', 'The text of locale "%locale%" is empty.', ['%locale%' => $locale]);
            }

            if (\strlen($text) > self::MAX_TEXT_BYTES || !mb_check_encoding($text, 'UTF-8')) {
                throw InvalidTerms::because('text_too_long', 'The text of locale "%locale%" is UTF-8 of 256 KiB at most.', ['%locale%' => $locale]);
            }

            $kept[$locale] = $text;
        }

        if ([] === $kept) {
            throw InvalidTerms::because('no_text', 'A version has the text of one locale at least.');
        }

        return $kept;
    }
}

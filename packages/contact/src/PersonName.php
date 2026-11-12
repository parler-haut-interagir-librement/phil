<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Contact\Exception\InvalidContact;

/**
 * The name of a person in its parts: the given name, additional names, the particle and the family name, a second
 * family name, an honorific, a generation and a credential, with the culture the name belongs to.
 *
 * The culture, a BCP 47 language tag, chooses the `NameConvention` that orders and sorts the name: `Charles de Gaulle`
 * in French sorts under `Gaulle`, `Herman Van Rompuy` in the Dutch of Belgium under `Van Rompuy`, `Bartók Béla` in
 * Hungarian is written family name first. An order given to the name wins over its culture's.
 *
 * ```php
 * $name = PersonName::of(given: 'Charles', family: 'Gaulle', particle: 'de', culture: 'fr');
 * $name->display();   // "Charles de Gaulle"
 * $name->sortKey();   // "Gaulle, Charles de"
 * ```
 */
final readonly class PersonName
{
    public const int MAX_ADDITIONAL = 10;

    private const string NATIVE_SCRIPT = '/^[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}ー々]+$/u';

    /**
     * @param list<string> $additional
     */
    private function __construct(
        public ?string $given,
        public ?string $family,
        public ?string $particle,
        public array $additional,
        public ?string $secondFamily,
        public ?string $honorific,
        public ?string $generation,
        public ?string $credential,
        public ?string $culture,
        public ?NameOrder $order,
    ) {
    }

    /**
     * Texts are trimmed, an empty one is none, and each is 255 characters or fewer, without line breaks nor other
     * control characters. A name has a given name or a family name; a particle goes with a family name.
     *
     * @param list<string> $additional the middle names, at most `MAX_ADDITIONAL` once the empty ones are dropped
     * @param ?string      $culture    a BCP 47 language tag, `fr-CA`; `_` is read as `-`
     */
    public static function of(
        ?string $given = null,
        ?string $family = null,
        ?string $particle = null,
        array $additional = [],
        ?string $secondFamily = null,
        ?string $honorific = null,
        ?string $generation = null,
        ?string $credential = null,
        ?string $culture = null,
        ?NameOrder $order = null,
    ): self {
        $given = self::text($given, 'given name');
        $family = self::text($family, 'family name');
        $particle = self::text($particle, 'particle');

        if (null === $given && null === $family) {
            throw InvalidContact::because('A person name has a given name or a family name.');
        }

        if (null !== $particle && null === $family) {
            throw InvalidContact::because('The particle of a person name goes with a family name.');
        }

        $additional = array_values(array_filter(array_map(static fn (string $name): ?string => self::text($name, 'additional name'), $additional), static fn (?string $name): bool => null !== $name));

        if (\count($additional) > self::MAX_ADDITIONAL) {
            throw InvalidContact::because(\sprintf('A person name has %d additional names or fewer.', self::MAX_ADDITIONAL));
        }

        return new self(
            $given,
            $family,
            $particle,
            $additional,
            self::text($secondFamily, 'second family name'),
            self::text($honorific, 'honorific'),
            self::text($generation, 'generation'),
            self::text($credential, 'credential'),
            self::culture($culture),
            $order,
        );
    }

    /**
     * The convention of the name's culture, or the one given.
     */
    public function convention(?NameConvention $convention = null): NameConvention
    {
        return $convention ?? NameConventions::for($this->culture);
    }

    public function displayOrder(?NameConvention $convention = null): NameOrder
    {
        return $this->order ?? $this->convention($convention)->order;
    }

    /**
     * The family name as written, its particle and its second family name included: `de Gaulle`, `García Márquez`,
     * `d'Estaing`.
     */
    public function familyName(): ?string
    {
        return null === $this->family ? null : $this->join([$this->surname(), $this->secondFamily]);
    }

    /**
     * The family name with its particle, without the second family name: `de Gaulle`, `García`.
     */
    public function surname(): ?string
    {
        return null === $this->family ? null : $this->withParticle($this->particle, $this->family);
    }

    /**
     * The name as it is written every day: given name, additional names and family name in the order of its culture —
     * family name, additional names and given name for a family name first —, then the generation. Chinese, Japanese
     * and Korean names written in their own characters are joined without a space: `毛泽东`.
     */
    public function display(?NameConvention $convention = null): string
    {
        $convention = $this->convention($convention);
        $parts = NameOrder::FamilyFirst === $this->displayOrder($convention)
            ? [$this->familyName(), ...$this->additional, $this->given]
            : [$this->given, ...$this->additional, $this->familyName()];

        return $this->join([$this->join($parts, $this->separator($convention)), $this->generation]);
    }

    /**
     * The name with its honorific and its credential: `Dr. Ada Lovelace, PhD`.
     */
    public function formal(?NameConvention $convention = null): string
    {
        $name = $this->join([$this->honorific, $this->display($convention)]);

        return null === $this->credential ? $name : $name.', '.$this->credential;
    }

    /**
     * The family name a list sorts by: without the leading words of its particle that its culture leaves out —
     * `Gaulle`, `La Fontaine`, `Van Rompuy` —, without its second family name.
     */
    public function sortFamily(?NameConvention $convention = null): ?string
    {
        if (null === $this->family) {
            return null;
        }

        $kept = null === $this->particle ? '' : $this->convention($convention)->sortedParticle($this->particle);

        return $this->withParticle('' === $kept ? null : $kept, $this->family);
    }

    /**
     * The text a list of people sorts by, with a collator of the reader's language: the sorted family name, then the
     * given and additional names and what the sort leaves out of the particle — `Gaulle, Charles de` —, then the
     * generation. What the key keeps of a particle starts with a capital, as a sorted list prints it: `Du Bellay,
     * Joachim`. A culture that sorts by the given name puts it first: `Giáp, Võ Nguyên`.
     */
    public function sortKey(?NameConvention $convention = null): string
    {
        $convention = $this->convention($convention);
        $separator = $this->separator($convention);

        if ($convention->sortsByGiven && null !== $this->given) {
            $rest = NameOrder::FamilyFirst === $this->displayOrder($convention) ? [$this->familyName(), ...$this->additional] : [...$this->additional, $this->familyName()];
            $key = $this->list([$this->given, $this->join($rest, $separator)]);
        } else {
            $sorted = $this->sortFamily($convention);
            $kept = null === $this->particle ? '' : $convention->sortedParticle($this->particle);
            $left = null === $this->particle ? '' : trim(mb_substr($this->particle, 0, mb_strlen($this->particle) - mb_strlen($kept)));
            $sorted = null === $sorted || '' === $kept ? $sorted : mb_strtoupper(mb_substr($sorted, 0, 1)).mb_substr($sorted, 1);
            $key = $this->list([$this->join([$sorted, $this->secondFamily], $separator), $this->join([$this->given, ...$this->additional, $left], $separator)]);
        }

        return $this->list([$key, $this->generation]);
    }

    public function equals(self $other): bool
    {
        return $this->given === $other->given && $this->family === $other->family && $this->particle === $other->particle
            && $this->additional === $other->additional && $this->secondFamily === $other->secondFamily
            && $this->honorific === $other->honorific && $this->generation === $other->generation
            && $this->credential === $other->credential && $this->culture === $other->culture && $this->order === $other->order;
    }

    /**
     * What separates the parts of the name when it is displayed: a space, or nothing for a name of a culture that joins
     * its own characters, written in them.
     */
    public function separator(?NameConvention $convention = null): string
    {
        if (!$this->convention($convention)->joinsNativeScript) {
            return ' ';
        }

        $parts = array_filter([$this->given, $this->family, $this->particle, $this->secondFamily, ...$this->additional], static fn (?string $part): bool => null !== $part);

        return array_all($parts, static fn (string $part): bool => 1 === preg_match(self::NATIVE_SCRIPT, $part)) ? '' : ' ';
    }

    private function withParticle(?string $particle, string $family): string
    {
        if (null === $particle) {
            return $family;
        }

        // An elided particle, `d'`, is written against the name.
        return $particle.(str_ends_with($particle, "'") || str_ends_with($particle, '’') ? '' : ' ').$family;
    }

    /**
     * @param list<?string> $parts
     */
    private function join(array $parts, string $separator = ' '): string
    {
        return implode($separator, array_filter($parts, static fn (?string $part): bool => null !== $part && '' !== $part));
    }

    /**
     * @param list<?string> $parts
     */
    private function list(array $parts): string
    {
        return $this->join($parts, ', ');
    }

    private static function text(?string $value, string $part): ?string
    {
        $text = trim($value ?? '');

        if (mb_strlen($text) > 255) {
            throw InvalidContact::tooLong($part);
        }

        if (!mb_check_encoding($text, 'UTF-8') || 1 === preg_match('/\p{Cc}/u', $text)) {
            throw InvalidContact::because(\sprintf('The %s of a person name is UTF-8 text without control characters.', $part));
        }

        return '' === $text ? null : $text;
    }

    private static function culture(?string $culture): ?string
    {
        $tag = str_replace('_', '-', trim($culture ?? ''));

        if ('' === $tag) {
            return null;
        }

        if (1 !== preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{1,8})*$/i', $tag) || \strlen($tag) > 35) {
            throw InvalidContact::because(\sprintf('"%s" is not a language tag.', mb_substr($tag, 0, 40)));
        }

        return $tag;
    }
}

<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a text option accepts: its length in characters, the characters allowed, the font and the zone it is printed
 * in, and its sources — typed by the buyer, picked among entries, or drawn among them after payment.
 *
 * The characters are a PCRE character class without its brackets — `\p{L}\p{N} '.,!?-` —, matched as Unicode. An
 * entry of the list is accepted as written: the catalogue chose it.
 */
final readonly class TextConstraints
{
    private const int MAX_LENGTH = 1000;

    /**
     * @param non-empty-list<TextSource> $sources
     * @param list<string>               $entries
     */
    private function __construct(
        public int $minLength,
        public int $maxLength,
        public ?string $characters,
        public ?string $font,
        public ?PrintZone $zone,
        public array $sources,
        public array $entries,
    ) {
    }

    /**
     * @param string|null      $characters a PCRE character class without its brackets; any character when `null`
     * @param string|null      $font       the name of the font the text is printed in, as the composer knows it
     * @param list<TextSource> $sources    typed by the buyer only, by default
     * @param list<string>     $entries    the texts to pick or draw from, for the `List` and `Drawn` sources
     */
    public static function of(int $maxLength, int $minLength = 1, ?string $characters = null, ?string $font = null, ?PrintZone $zone = null, array $sources = [TextSource::Free], array $entries = []): self
    {
        if ($minLength < 1 || $maxLength < $minLength || $maxLength > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A text holds from 1 to %d characters, at least its minimum; %d to %d is not that.', self::MAX_LENGTH, $minLength, $maxLength));
        }

        if (null !== $characters && ('' === $characters || false === @preg_match('/^['.$characters.']*$/uD', ''))) {
            throw InvalidValue::because(\sprintf('"%s" is not a character class.', $characters));
        }

        if (null !== $font && 1 !== preg_match('/^\S(?:.{0,126}\S)?$/uD', $font)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of a font: 1 to 128 characters, without spaces around.', $font));
        }

        if ([] === $sources || \count($sources) !== \count(array_unique(array_map(static fn (TextSource $source): string => $source->value, $sources)))) {
            throw InvalidValue::because('A text option has its sources, each once.');
        }

        $listed = \in_array(TextSource::List, $sources, true) || \in_array(TextSource::Drawn, $sources, true);

        if ($listed !== ([] !== $entries)) {
            throw InvalidValue::because('A text option picked or drawn from a list has its entries, and only then.');
        }

        foreach ($entries as $entry) {
            if ('' === trim($entry)) {
                throw InvalidValue::because('An entry of a text option is not blank.');
            }
        }

        if (\count($entries) !== \count(array_unique($entries))) {
            throw InvalidValue::because('The entries of a text option are each listed once.');
        }

        return new self($minLength, $maxLength, $characters, $font, $zone, $sources, $entries);
    }

    public function allows(TextSource $source): bool
    {
        return \in_array($source, $this->sources, true);
    }

    /**
     * Why the text is refused, or `null` when it is accepted: an entry when picking from the list is allowed, or a
     * text of the right length and characters when typing is.
     */
    public function refusal(string $text): ?string
    {
        if ($this->allows(TextSource::List) && \in_array($text, $this->entries, true)) {
            return null;
        }

        if (!$this->allows(TextSource::Free)) {
            return 'it is not one of the entries';
        }

        $length = mb_strlen($text);

        if ($length < $this->minLength || $length > $this->maxLength) {
            return \sprintf('it holds %d characters, not %d to %d', $length, $this->minLength, $this->maxLength);
        }

        if (null !== $this->characters && 1 !== preg_match('/^['.$this->characters.']*$/uD', $text)) {
            return 'it holds characters the option does not allow';
        }

        return null;
    }
}

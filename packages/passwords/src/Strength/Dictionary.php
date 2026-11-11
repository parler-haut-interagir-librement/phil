<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

use Phil\Passwords\Exception\InvalidDictionary;

/**
 * Ranked entries the estimator looks for in a password, lowercase, the most common first: the rank of an entry is its
 * position from 1, and a word of rank 10 costs ten guesses.
 *
 * The bundled dictionaries are the common passwords, common first names and surnames, and common words in English,
 * French, Spanish and German, all written for this library; `of()` takes the application's own, such as the name of the
 * site or the words of its domain.
 */
final readonly class Dictionary
{
    public const string PASSWORDS = 'passwords';

    public const string NAMES = 'names';

    public const string SURNAMES = 'surnames';

    public const string ENGLISH = 'english';

    public const string FRENCH = 'french';

    public const string SPANISH = 'spanish';

    public const string GERMAN = 'german';

    public const string USER_INPUTS = 'user_inputs';

    /** The longest entry kept, in characters: a longer one would only make each analysis slower. */
    public const int MAX_ENTRY_LENGTH = 64;

    private const string DATA = __DIR__.'/../../data/dictionaries/';

    /**
     * @param array<array-key, int> $ranks
     */
    private function __construct(
        public string $name,
        private array $ranks,
        public int $maxLength,
    ) {
    }

    /**
     * @param iterable<string> $entries the most common first; case is ignored and a repeated entry keeps its first rank
     *
     * @throws InvalidDictionary when the name is empty or an entry is empty or longer than MAX_ENTRY_LENGTH
     */
    public static function of(string $name, iterable $entries): self
    {
        if ('' === $name) {
            throw new InvalidDictionary('A dictionary has a name.');
        }

        $ranks = [];
        $maxLength = 0;

        foreach ($entries as $entry) {
            $entry = mb_strtolower($entry);
            $length = mb_strlen($entry);

            if (0 === $length || $length > self::MAX_ENTRY_LENGTH) {
                throw new InvalidDictionary(\sprintf('The entries of the dictionary "%s" have 1 to %d characters.', $name, self::MAX_ENTRY_LENGTH));
            }

            $ranks[$entry] ??= \count($ranks) + 1;
            $maxLength = max($maxLength, $length);
        }

        return new self($name, $ranks, $maxLength);
    }

    /**
     * Every bundled dictionary.
     *
     * @return list<self>
     */
    public static function bundled(): array
    {
        return array_map(self::named(...), [self::PASSWORDS, self::NAMES, self::SURNAMES, self::ENGLISH, self::FRENCH, self::SPANISH, self::GERMAN]);
    }

    /**
     * A bundled dictionary, by one of the constants of this class but USER_INPUTS.
     *
     * @throws InvalidDictionary when no bundled dictionary has that name
     */
    public static function named(string $name): self
    {
        if (!\in_array($name, [self::PASSWORDS, self::NAMES, self::SURNAMES, self::ENGLISH, self::FRENCH, self::SPANISH, self::GERMAN], true)) {
            throw new InvalidDictionary(\sprintf('No dictionary named "%s" is bundled.', $name));
        }

        /** @var list<string> $entries */
        $entries = require self::DATA.$name.'.php';

        return self::of($name, $entries);
    }

    /**
     * The dictionary of what the application knows of the person — name, email address, username, birth date —, and of
     * the parts of three characters or more in each — split on `@` and whitespace, and into runs of letters and digits —,
     * so that `jean.dupont@example.com` also gives `jean.dupont`, `jean` and `dupont`. Entries longer than MAX_ENTRY_LENGTH are cut.
     *
     * @param list<string> $inputs
     */
    public static function ofUserInputs(array $inputs): self
    {
        $entries = [];

        foreach ($inputs as $input) {
            $input = mb_substr(trim($input), 0, self::MAX_ENTRY_LENGTH);

            if ('' === $input) {
                continue;
            }

            $entries[] = $input;

            foreach ([...self::split('/[@\s]+/u', $input), ...self::split('/[^\p{L}\p{N}]+/u', $input)] as $part) {
                if (mb_strlen($part) >= 3) {
                    $entries[] = $part;
                }
            }
        }

        return self::of(self::USER_INPUTS, $entries);
    }

    /**
     * @return list<string>
     */
    private static function split(string $pattern, string $input): array
    {
        $parts = preg_split($pattern, $input);

        return false === $parts ? [] : $parts;
    }

    /**
     * Each entry to its rank; PHP turns the keys of entries written in digits, such as `123456`, into integers.
     *
     * @return array<array-key, int>
     */
    public function ranks(): array
    {
        return $this->ranks;
    }

    public function rank(string $lowercaseEntry): ?int
    {
        return $this->ranks[$lowercaseEntry] ?? null;
    }

    public function count(): int
    {
        return \count($this->ranks);
    }
}

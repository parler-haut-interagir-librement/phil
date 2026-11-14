<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack;
use Random\Randomizer;

/**
 * What an anonymizer is given beside the value: its column's options, the locale pack, the run's random source,
 * and the run's salt for the anonymizers that derive a value from the original one.
 *
 * **The salt makes a derived value one-way.** Without it, a hashed email is found again by hashing a list of
 * candidates; with a salt drawn for the run and never stored, it is not. Within one run the same original value
 * gives the same result, so a value repeated across tables still joins.
 */
final readonly class AnonymizationContext
{
    public function __construct(
        public AnonymizerOptions $options,
        public LocalePack $locale,
        public Randomizer $random,
        public string $salt,
    ) {
    }

    /**
     * One entry of a non-empty list, drawn from the run's random source.
     *
     * @param non-empty-list<string> $values
     */
    public function pick(array $values): string
    {
        return $values[$this->random->getInt(0, \count($values) - 1)];
    }

    /**
     * The pattern with each `#` replaced by a digit, `?` by an uppercase letter and `*` by either; `\` writes the
     * next character as it is. The pattern is read byte by byte: a multibyte character is copied unchanged.
     */
    public function fill(string $pattern): string
    {
        $filled = '';
        $escaped = false;

        foreach (str_split($pattern) as $character) {
            if ($escaped) {
                $filled .= $character;
                $escaped = false;

                continue;
            }

            $filled .= match ($character) {
                '\\' => '',
                '#' => $this->random->getBytesFromString('0123456789', 1),
                '?' => $this->random->getBytesFromString('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 1),
                '*' => $this->random->getBytesFromString('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 1),
                default => $character,
            };
            $escaped = '\\' === $character;
        }

        return $filled;
    }

    /**
     * A digest of the original value under the run's salt: equal values give equal digests within one run.
     */
    public function digest(string|int|float|bool $value, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, (string) $value, $this->salt);
    }
}

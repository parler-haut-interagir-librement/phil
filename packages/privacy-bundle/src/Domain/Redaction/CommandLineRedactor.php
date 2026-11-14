<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * Redacts the value of every long option whose name the denylist denies, wherever a command line appears in a
 * string.
 *
 * A console error is logged with the command line that ran it (`Error thrown while running command
 * "app:import --password=hunter2"`, the input under `command`), so a secret passed as an option reaches the
 * log in the message and in a context value whose key is not denied. **The option name is the key**: it is
 * compared by the denylist, normalised and with its patterns, so `--password`, `--db-password` under
 * `*password*` and `--api-token` under `*token*` are all caught.
 *
 * - **`--name=value`**: the value is redacted whatever it is, empty included: `--password=[redacted]`.
 * - **`--name value`**: the next word is redacted, unless it starts with `--` — then the option is a flag
 *   followed by another long option, or by the `--` that ends the options, and there is no value to hide. A
 *   word that starts with a single `-` is redacted: Symfony does not quote `-abc`, so a generated password or
 *   token that starts with a dash reaches the line bare, and a short option after a denied flag is the cheaper
 *   loss.
 * - A value is a shell word: a `"…"` string, or a run of plain characters, `'…'` strings and `\` escapes, so
 *   `'it'\''s'`, which Symfony writes for `it's`, goes as one value. A plain run stops at whitespace and at `"`,
 *   which is where a log message that quotes the command line closes it. It does not stop at `)`: Symfony quotes
 *   any word that holds one, so a bare `)` after a value is taken with it rather than risk leaving a tail. A
 *   quote that never closes runs to the end of the text, for the same reason. A value is read in runs, never one
 *   character at a time, and nothing is given back once read: a value of any length, a megabyte included, is
 *   redacted like a short one.
 * - **The name must start the option**: `--` at the start of the text, or after whitespace, a quote, `(` or
 *   `=`.
 *
 * **Short options are opt-in, through a declared table.** A letter says nothing about itself — `-p` is
 * `--password` for one command and `--port` for the next — so a short option is redacted only when the table
 * maps its letter to a long name, and the denylist denies that name: `['p' => 'password']` redacts `-p secret`,
 * `-psecret` and `-p=secret`. Without a table, no short option is read. Letters are case-sensitive, as console
 * shortcuts are. The letter may close a set of flags, `-vp secret`, and the rest of the word after it is the
 * value, as a console parser reads it; when a letter before it took a value instead, the redaction takes more
 * than the value, which is the side it errs on. The separate value follows the long option rule: redacted
 * unless it starts with `--`. A short option starts where a long one does, so `re-print` is not read.
 *
 * The value of an option that is not denied is kept, and read again for an option it might quote.
 *
 * Redaction errs towards too much: `--token --force` keeps `--force`, but `--token release` loses `release`
 * even when `--token` was a flag and `release` an argument, and `--token -v` loses `-v`.
 */
final readonly class CommandLineRedactor
{
    private const string OPTION = <<<'REGEX'
        ~(?<lead>^|[\s"'(=])--(?<name>[A-Za-z0-9][A-Za-z0-9_.-]*)(?:(?<equals>=)|(?<space>\s+)(?=[^\s-]|-(?!-)))(?<value>"(?:[^"\\]++|\\.)*+(?:"|\z)|(?:'[^']*+(?:'|\z)|\\.|[^\s'"\\]++)*+)~
        REGEX;

    private const string VALUE = <<<'REGEX'
        (?<value>"(?:[^"\\]++|\\.)*+(?:"|\z)|(?:'[^']*+(?:'|\z)|\\.|[^\s'"\\]++)*+)
        REGEX;

    private const string LETTER_PATTERN = '/^[A-Za-z]$/';

    private const string LONG_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/';

    /**
     * The pattern of the short options to redact, or null when no declared letter maps to a denied name.
     */
    private ?string $shortOption;

    /**
     * @param array<array-key, mixed> $shortOptions each letter, under itself, to the long name it stands for
     *
     * @throws InvalidPrivacyValue when a letter or a long name is malformed
     */
    public function __construct(
        private Denylist $denylist,
        array $shortOptions = [],
    ) {
        $letters = '';

        foreach ($shortOptions as $letter => $name) {
            $letter = (string) $letter;

            if (1 !== preg_match(self::LETTER_PATTERN, $letter)) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" is not a short option: a short option is one ASCII letter, such as "p".', $letter));
            }

            if (!\is_string($name) || 1 !== preg_match(self::LONG_NAME_PATTERN, $name)) {
                throw InvalidPrivacyValue::because(\sprintf('The short option "%s" must map to a long option name without its dashes, such as "password".', $letter));
            }

            if ($denylist->denies($name)) {
                $letters .= $letter;
            }
        }

        $this->shortOption = '' === $letters ? null : \sprintf(
            '~(?<lead>^|[\s"\'(=])-(?<flags>[A-Za-z]*?)(?<letter>[%s])(?:(?<equals>=)|(?<space>\s+)(?=[^\s-]|-(?!-))|(?=[^\s=]))%s~',
            $letters,
            self::VALUE,
        );
    }

    public function redact(string $text): string
    {
        $redacted = preg_replace_callback(
            self::OPTION,
            function (array $match): string {
                $name = $match['name'];
                $kept = $this->denylist->denies($name)
                    ? UrlRedactor::PLACEHOLDER
                    // A value kept is still read: `--message "--password=x"` quotes an option.
                    : $this->redact($match['value']);

                return $match['lead'].'--'.$name.($match['equals'] ?? '').($match['space'] ?? '').$kept;
            },
            $text,
            flags: \PREG_UNMATCHED_AS_NULL,
        );

        if (null === $redacted) {
            // A pattern that fails (a PCRE limit the runtime lowered) must not hand the text back unredacted.
            return UrlRedactor::PLACEHOLDER;
        }

        if (null === $this->shortOption) {
            return $redacted;
        }

        $redacted = preg_replace_callback(
            $this->shortOption,
            static fn (array $match): string => $match['lead'].'-'.$match['flags'].$match['letter'].($match['equals'] ?? '').($match['space'] ?? '').UrlRedactor::PLACEHOLDER,
            $redacted,
            flags: \PREG_UNMATCHED_AS_NULL,
        );

        return $redacted ?? UrlRedactor::PLACEHOLDER;
    }
}

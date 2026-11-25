# Changelog

All notable changes to `phil/passwords` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-24

Documentation and development only.

### Added

- The [ASVS 5.0 mapping](doc/asvs.md).
- Tests of a password of 200 characters, and of passwords hashed and checked exactly as typed: surrounding spaces,
  capitals, composed and decomposed accents, invalid UTF-8.

### Changed

- The development requirements ask for the latest minor of each dependency (`symfony/http-client-contracts` 3.8).

## 0.2.0-alpha - 2026-11-15

### Added

- `PassphraseGenerator::accepts()`: whether every passphrase of a policy gives back its draws on the generator's list.

### Changed

- `PassphraseGenerator::generate()` and `entropyBits()` throw `InvalidPolicy` for a policy whose passphrases do not
  read back, instead of counting draws that write the same passphrase as different ones: words that run together
  with the separator (with an empty separator, the compound words of the bundled lists, such as `week` + `end` and
  `weekend`; they still read back with `Capitalization::Words`), checked by the Sardinas–Patterson test on the forms
  the policy writes, and a digit appended to a word that writes another word of the list (`ab` + `1` and `ab1`). An
  empty separator with `Capitalization::None`, `First` or `Random` on a bundled list is refused.
- `MinimumStrength`: the `{{ score }}` and `{{ min_score }}` parameters of its violation are integers.
- `NotCompromised`: the `{{ occurrences }}` parameter of its violation is an integer.

## 0.1.1-alpha - 2026-11-14

### Added

- `Wordlist::bitsPerCapitalisedWord()`, `Wordlist::bitsPerRandomlyCapitalisedWord()` and `Wordlist::capitalise()`.

### Fixed

- `PassphraseGenerator::entropyBits()` counts capitalisation on the forms it writes: a word without case, a word
  already capitalised, or two words of a custom list that differ only by the case of their first letter no longer add
  a bit each with `Random`, and the forms two words share are no longer counted twice with `First` and `Words`.
  `Wordlist::of()` computes the entropy once, as Shannon entropy over the equally likely draws of a word; the bundled
  lists keep their values.

## 0.1.0-alpha - 2026-11-11

### Added

- `Strength\StrengthEstimator`: `estimate()` finds the patterns of a password — dictionary words as written, reversed
  and with substitutions, keyboard walks, sequences, repeats, dates and years —, takes the cheapest cover of the
  password by them, and returns a `Strength`: guesses, `Score` from 0 to 4, the `PatternMatch`es of the cover,
  `CrackTimes` in four settings and `Feedback`. The first 128 characters are analysed; invalid UTF-8 is read with its
  bytes replaced; what the application knows of the person, up to 32 inputs, becomes a dictionary of its own.
- The cover search: `l!` times the product of the guesses of `l` patterns plus a penalty for each pattern; at most 8
  patterns and runs of at most 20 characters guessed one by one after a pattern, both far above the best score.
- `Strength\Dictionary`: ranked entries, the bundled common passwords, first names, surnames and common words in
  English, French, Spanish and German, written for the library; `of()` for the application's own; `ofUserInputs()`.
- `Strength\KeyboardLayout`: staggered keyboards in half keys and aligned keypads, with QWERTY, AZERTY, QWERTZ and the
  numeric keypad bundled; `staggered()` and `aligned()` for others.
- `Strength\Warning` and `Strength\Suggestion`, translated in English and French in `translations/phil_passwords.*.php`;
  `Strength\FeedbackMessages` reads them without a translator.
- `Breach\BreachChecker`: checks a password against a `Breach\Contract\BreachedPasswordSource` and returns a
  `BreachCheck` — `Compromised`, `NotFound` or `Unavailable` with the failure — without throwing.
- `Breach\Adapter\PwnedPasswordsSource`: the Pwned Passwords range API by k-anonymity, through
  `symfony/http-client-contracts`, with padding, a user agent, a timeout and a bound on the answer; every failure is a
  `BreachCheckUnavailable`. `Breach\RangeResponse` reads a range; `Breach\InMemoryBreachSource` holds hashes in memory.
- `Generator\PasswordGenerator` and `Generator\PasswordPolicy`: random passwords of 4 to 512 characters from character
  sets, without the ambiguous characters on demand, each set held when required, with their exact entropy.
- `Generator\PassphraseGenerator`, `Generator\PassphrasePolicy` and `Generator\Wordlist`: passphrases of 3 to 64 words
  with a separator, a capitalisation and a digit, from the bundled lists in English, French, Spanish and German or the
  application's own of at least 256 words, with their entropy.
- `Validator\MinimumStrength` and `Validator\NotCompromised`, with their validators and their messages in English and
  French in `translations/validators.*.php`; `Contract\PasswordUserInputs` gives the estimator what the validated
  object knows of the person.
- `Exception\PasswordsException`, implemented by `InvalidPolicy`, `InvalidWordlist`, `InvalidDictionary` and
  `BreachCheckUnavailable`.

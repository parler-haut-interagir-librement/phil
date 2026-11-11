# Changelog

All notable changes to `phil/passwords` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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

# Reference

## Strength

| Type | Role |
|---|---|
| `Strength\StrengthEstimator` | Estimates the guesses of a password and returns a `Strength`. |
| `Strength\Strength` | Guesses, score, patterns, crack times, feedback, lengths. |
| `Strength\Score` | 0 to 4, by the order of magnitude of the guesses. |
| `Strength\PatternMatch` | A part of the password that follows a pattern, with its guesses and the fields of its pattern. |
| `Strength\Pattern` | Dictionary, spatial, sequence, repeat, date, year, bruteforce. |
| `Strength\CrackTimes` | The seconds to try the guesses in four settings. |
| `Strength\Feedback` | A warning and suggestions. |
| `Strength\Warning`, `Strength\Suggestion` | What makes a password weak and what would help, with their translation keys. |
| `Strength\FeedbackMessages` | The sentences of the feedback in a locale, without a translator. |
| `Strength\Dictionary` | Ranked entries looked for in passwords; the bundled ones and the user inputs. |
| `Strength\KeyboardLayout` | Keys and their neighbours; the bundled layouts. |

## Breaches

| Type | Role |
|---|---|
| `Breach\BreachChecker` | Checks a password against a source, without throwing. |
| `Breach\BreachCheck`, `Breach\BreachStatus` | The outcome of a check. |
| `Breach\Contract\BreachedPasswordSource` | The port of the breach sources. |
| `Breach\Adapter\PwnedPasswordsSource` | The Pwned Passwords range API by k-anonymity. |
| `Breach\InMemoryBreachSource` | Hashes held in memory. |
| `Breach\RangeResponse` | Reads an answer of the range API. |

## Generation

| Type | Role |
|---|---|
| `Generator\PasswordGenerator`, `Generator\PasswordPolicy`, `Generator\CharacterSet` | Random passwords. |
| `Generator\PassphraseGenerator`, `Generator\PassphrasePolicy`, `Generator\Capitalization` | Passphrases. |
| `Generator\Wordlist` | The words of passphrases. |

## Validation

| Type | Role |
|---|---|
| `Validator\MinimumStrength`, `Validator\MinimumStrengthValidator` | A password scored at least a minimum. |
| `Validator\NotCompromised`, `Validator\NotCompromisedValidator` | A password absent from known breaches. |
| `Contract\PasswordUserInputs` | What a validated object knows of the person. |

## Exceptions

`Exception\PasswordsException`, `InvalidPolicy`, `InvalidWordlist`, `InvalidDictionary`, `BreachCheckUnavailable`: see
[Exceptions](exceptions.md).

Classes under `Strength\Internal` are internal: their names and signatures may change in any version.

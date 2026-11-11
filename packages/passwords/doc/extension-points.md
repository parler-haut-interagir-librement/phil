# Extension points

What an application implements, replaces or relies on.

## Dictionaries

`StrengthEstimator` takes the list of `Dictionary`s it looks for words in: the bundled ones by default. Add the
application's own — the name of the site, its products, words of its domain — with `Dictionary::of()`, ranked from the
most common, beside `Dictionary::bundled()`. The name of a dictionary is free; `passwords`, `names`, `surnames` and
`user_inputs` give their own warnings.

## Keyboard layouts

`StrengthEstimator` takes the list of `KeyboardLayout`s it looks for walks on: QWERTY, AZERTY, QWERTZ and the keypad by
default. `KeyboardLayout::staggered()` describes another typewriter keyboard by its rows and their offsets in half keys,
`KeyboardLayout::aligned()` another keypad.

## User inputs

`estimate()` takes what the application knows of the person. A validated object gives them by implementing
`Contract\PasswordUserInputs`.

## Breach sources

`Breach\Contract\BreachedPasswordSource` is the port of the breach checks: `PwnedPasswordsSource` over HTTP,
`InMemoryBreachSource` in memory, or the application's own — a mirror reached otherwise, a decorator with a circuit
breaker or a cache. A source throws `BreachCheckUnavailable` when it cannot answer. See [Breach checks](breaches.md).

## Wordlists and randomness

`PassphraseGenerator` takes a `Wordlist`: a bundled one or `Wordlist::of()`. Both generators take a
`Random\Randomizer`: the secure engine by default, a seeded one in a test.

## Translations

The feedback and the constraints are translated by keys: `Warning::translationKey()` and
`Suggestion::translationKey()` in the `phil_passwords` domain, the constraint messages in `validators`. An application
adds a locale by writing `phil_passwords.<locale>.php` and `validators.<locale>.php` in its own translation path.

## Stable values

`Score`, `Pattern`, `Warning`, `Suggestion`, `BreachStatus`, `CharacterSet` and `Capitalization` are backed enums whose
values are stable, so that an application stores them and translates them by them.

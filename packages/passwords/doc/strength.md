# Estimating strength

## The estimate

```php
$strength = $estimator->estimate($password, ['jean.dupont@example.com', 'Jean Dupont']);
```

| Member of `Strength` | What it holds |
|---|---|
| `guesses` | The guesses of the cheapest cover of the password, a float. |
| `guessesLog10()` | Their order of magnitude. |
| `score` | A `Score` from 0 to 4. |
| `sequence` | The `PatternMatch`es of the cover, in order. |
| `crackTimes` | `CrackTimes`: the seconds to try the guesses online with and without throttling, offline against a slow and a fast hash. |
| `feedback` | `Feedback`: a `Warning` or null, and `Suggestion`s. |
| `length`, `analysedLength`, `isTruncated()` | The characters of the password and those analysed. |

A strength and its matches hold parts of the password: they are shown to the person who typed it, never logged nor
stored.

## The patterns

| `Pattern` | Found | Guesses |
|---|---|---|
| `Dictionary` | Each part of the password that is an entry of a dictionary, as written, reversed, or with characters that stand for letters (`@` for `a`, `0` for `o`, `1` for `i` or `l`…) | The rank of the entry, times its variations of case, times its variations of substitutions, twice when reversed |
| `Spatial` | Runs of three keys or more, each next to the previous on a keyboard | The walks of up to that length and that many turns from any key, times the variations of the shifted keys |
| `Sequence` | Runs of three characters or more at a steady step of 1 to 5 code points, up or down | 4, 10 or 26 by the first character, twice down, times the length |
| `Repeat` | A part written twice or more in a row | The guesses of its base, analysed in turn, times the repeats |
| `Date` | A day, a month and a year of two or four digits, with a separator or without, in any order | The years between the date and the reference year, at least 20, times 365, four times with a separator |
| `Year` | A year from 1900 to 2099 | The years between it and the reference year, at least 20 |
| `Bruteforce` | Whatever the patterns leave | 10 for each character |

Case variations count 1 for a word in lowercase, 2 for a word capitalised, in uppercase or with its last letter in
uppercase, and the ways of placing the uppercase letters otherwise. The reference year is the current year unless the
estimator is built with one.

## The cover

The estimator takes the cheapest way to cover the password with patterns, characters that follow none being guessed
one by one: an attacker who tries sequences of `l` patterns pays `l!` times the product of their guesses, plus 10 000
to the power `l - 1` for having tried the shorter sequences first. A part of a longer password costs at least 10
guesses for one character and 50 for more, so that cutting it in small patterns is never cheaper.

## The score

| `Score` | Guesses |
|---|---|
| `VeryWeak` (0) | fewer than 10³ |
| `Weak` (1) | fewer than 10⁶ |
| `Fair` (2) | fewer than 10⁸ |
| `Strong` (3) | fewer than 10¹⁰ |
| `VeryStrong` (4) | 10¹⁰ or more |

## The feedback

A password scored 3 or 4 gets no feedback. Otherwise the longest pattern of the cover gives the warning — a common
password and its rank, a word or a name alone, a keyboard walk, a repeat, a sequence, a year, a date, what the
application knows of the person — and suggestions: add a word, then what the pattern calls for, such as capitalisation
or substitutions that barely help. An empty password is told to use a few words.

`Warning` and `Suggestion` are string-backed enums whose values are stable. Their translation keys,
`warning.<value>` and `suggestion.<value>`, are in the `phil_passwords` domain, in English and French. Without a
translator, `FeedbackMessages` reads the same files:

```php
$messages = FeedbackMessages::forLocale('fr_CA');   // fr_CA, then fr, then en
$sentences = $messages->describe($strength->feedback);
```

## What the application knows

The user inputs become a dictionary of their own, `user_inputs`, ranked in their order: each input, and its parts of
three characters or more split on `@` and whitespace and into runs of letters and digits. Up to 32 inputs are kept,
each cut to 64 characters. A password built on them gets the warning `UserInput`.

## Bounds

| Bound | Value | Why |
|---|---|---|
| Characters analysed | 128 | The work of the cover grows with the cube of the length at worst; a longer password only costs more guesses. |
| Patterns of a cover | 8 | A cover of more costs over 10²⁸, far above the best score. |
| Run guessed one by one after a pattern | 20 characters | A longer run costs over 10²⁰; the run of the whole password stays. |
| User inputs | 32, of 64 characters | Each becomes entries looked up in every analysis. |
| Dictionary entries | 64 characters | A longer entry only makes each analysis slower. |

Above 10²⁰ guesses, the bounds may give more guesses than the cheapest cover: the score is 4 either way. An ordinary
password is analysed in well under a millisecond, the most hostile input of 128 characters in a few tens of
milliseconds; see [Testing](testing.md#the-benchmark).

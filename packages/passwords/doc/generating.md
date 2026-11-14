# Generating passwords

Both generators draw from `Random\Randomizer` over the `Secure` engine — the cryptographically secure source of the
system, as `random_int()` — unless a test gives them a seeded one.

## Random passwords

```php
$generator = new PasswordGenerator();
$generator->generate();                                                        // the default policy
$generator->generate(new PasswordPolicy(32, [CharacterSet::Lowercase, CharacterSet::Digits]));
$generator->generate(new PasswordPolicy()->withLength(16)->withoutAmbiguous());
```

| Member of `PasswordPolicy` | Default | Effect |
|---|---|---|
| `length` | 20 | 4 to 512 characters. |
| `sets` | the four `CharacterSet`s | Lowercase, uppercase, digits, symbols; repeated sets count once. |
| `excludeAmbiguous` | `false` | Leaves out `I`, `l`, `1`, `\|`, `O`, `0` and `o`. |
| `requireEachSet` | `true` | Every password holds at least one character of each set. |

The symbols are `!#$%&()*+,-./:;<=>?@[]^_{|}~`: no space, quote nor backslash, which other systems often mishandle.
Each character is drawn uniformly from the sets together; with each set required, a draw that misses one is drawn
again whole, so that every password of the policy stays equally likely.

`entropyBits()` is the base-2 logarithm of the number of passwords the policy allows, those that miss a required set
left out: about 130 bits for the default policy.

## Passphrases

```php
$generator = new PassphraseGenerator(Wordlist::french());
$generator->generate();                                                        // seven words joined by dashes
$generator->generate(new PassphrasePolicy(5, ' ', Capitalization::Words, includeDigit: true));
$generator->entropyBits(new PassphrasePolicy());
```

| Member of `PassphrasePolicy` | Default | Effect |
|---|---|---|
| `words` | 7 | 3 to 64 words, each drawn uniformly and independently. |
| `separator` | `-` | Up to 8 bytes and no letter, so that it never merges with the words; may be empty. |
| `capitalization` | `Capitalization::None` | `First` capitalises the first word, `Words` every word, `Random` each word at random. |
| `includeDigit` | `false` | Appends a random digit to a random word. |

`entropyBits()` counts the words for an attacker who knows the list and the policy: `words × log2(list size)`, and
`log2(10 × words)` with the digit. Seven words of the bundled lists give 66 to 70 bits.

Capitalisation is counted on the forms it writes, as Shannon entropy over the equally likely draws of a word. Of `n`
draws, a form written by `c` of them makes the entropy `log2(n) − Σ c·log2(c) / n`:

| Capitalisation | Draws of a word | Each form |
|---|---|---|
| `None` | The `n` words | Written once |
| `First` (first word), `Words` | The `n` words, capitalised | Written by every word with that capitalised form |
| `Random` | The `2n` words, as they are or capitalised | Written by every draw that gives it |

A list whose words each give a capitalised form of their own, as the bundled lists do, keeps `log2(list size)` for
`First` and `Words`, and gains exactly one bit per word with `Random`. A word without case (digits, symbols, a script
without case), a word already capitalised, or two words that differ only by the case of their first letter give less.
`Wordlist::bitsPerCapitalisedWord()` and `bitsPerRandomlyCapitalisedWord()` hold the result, which `Wordlist::of()`
computes once, when it reads the list.

## Wordlists

| Wordlist | Words |
|---|---|
| `Wordlist::english()` (`en`) | 1 068 |
| `Wordlist::french()` (`fr`) | 866 |
| `Wordlist::spanish()` (`es`) | 723 |
| `Wordlist::german()` (`de`) | 689 |

The bundled words are common words of three to ten lowercase ASCII letters, without accents, so that a passphrase is
typed on any keyboard. `Wordlist::of($name, $words)` takes the application's own: at least 256 words, each of 1 to 32
characters, without whitespace, none repeated.

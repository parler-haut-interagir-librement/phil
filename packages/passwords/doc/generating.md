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

`entropyBits()` counts the words for an attacker who knows the list and the policy: `words × log2(list size)`, one
more bit per word with `Random` capitalisation, and `log2(10 × words)` with the digit. Seven words of the bundled
lists give 66 to 70 bits.

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

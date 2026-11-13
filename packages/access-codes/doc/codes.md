# Codes

A code is a number of symbols drawn uniformly from an alphabet, then a check symbol. `CodeFormat` says what a code
looks like, draws new ones and reads what a person typed.

## The alphabet

`CodeAlphabet` holds 16 to 36 distinct digits and capital letters of ASCII. The default is Crockford's Base32,
`0123456789ABCDEFGHJKMNPQRSTVWXYZ`: no `I`, `L` or `O`, which a reader takes for `1` and `0`, and no `U`.

```php
use Phil\AccessCodes\CodeAlphabet;

$alphabet = new CodeAlphabet();                       // Crockford's Base32, 5 bits per symbol
$hexadecimal = new CodeAlphabet('0123456789ABCDEF');  // 4 bits per symbol
```

When a code is read, the text is put in capitals, its hyphens and spaces are dropped, and the letters people mistype
for a digit are taken for it: `O` for `0`, `I` and `L` for `1`. A letter is taken for its digit only when the letter is
outside the alphabet and the digit inside it, so an alphabet that holds `O` keeps it.

## The format

```php
use Phil\AccessCodes\CodeFormat;

$format = new CodeFormat();   // 12 symbols of Crockford's Base32, 60 bits, a check symbol, groups of 4
$format->display('7KQ2M9XDH4TBH');   // "7KQ2-M9XD-H4TB-H"
$format->parse(' 7kq2 m9xd-h4tb-h'); // "7KQ2M9XDH4TBH"

$longer = CodeFormat::withEntropy(80);                    // the shortest format of 80 bits: 16 symbols
$plain = new CodeFormat(length: 10, checkSymbol: false, groupSize: 5);
```

| Argument | Default | Bounds |
|---|---|---|
| `alphabet` | Crockford's Base32 | Any `CodeAlphabet` |
| `length` | 12 | 1 to 64 random symbols, carrying 40 bits at the least |
| `checkSymbol` | `true` | — |
| `groupSize` | 4 | 0 to 64 symbols between two hyphens when a code is shown; 0 shows one block |

The floor of 40 bits holds whatever the alphabet: 8 symbols of Crockford's Base32, 10 of hexadecimal. With the keyed
hash of the store and a limit on attempts, guessing one code of a batch of a million at 40 bits takes about a million
attempts per hit; 60 bits, the default, about 10¹⁸.

`generate()` draws the symbols with `Random\Randomizer::getBytesFromString()`, on the `Secure` engine unless a test
gives another, then adds the check symbol. The canonical form of a code is its symbols without separators: it is what
is hashed and what a redemption link carries.

## The check symbol

The check symbol is the Luhn mod N algorithm over the alphabet: from the right, every other symbol's value is doubled
and its digits in base N are added, and the check symbol brings the sum to a multiple of N. It catches every mistyped
symbol and most swaps of two neighbours, so that a code miscopied from a printed card is refused before any lookup.

## Reading a code

`parse()` returns the canonical form, or throws `InvalidCode`, whose message never repeats the text:

| Refused | Message |
|---|---|
| More than 256 characters as typed | `A code is at most 256 characters as typed; …` |
| The wrong number of symbols | `A code has 13 symbols; 12 were given.` |
| A character outside the alphabet | `The code holds a symbol outside its alphabet.` |
| A check symbol that does not match | `The check symbol of the code does not match: a symbol was mistyped.` |

A redemption turns every one of these into the outcome `Unknown`; see [Redemption](redemption.md).

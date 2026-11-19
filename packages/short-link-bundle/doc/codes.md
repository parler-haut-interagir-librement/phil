# Codes

A code is the path of a short link: `https://go.example.com/Xk3mQp`.

## The Sqids format

A drawn code is a random number written in the format of the [Sqids](https://sqids.org) specification. The same
alphabet, minimum length and blocklist give the same identifier as the other implementations of the format, so a code
can be decoded by another program that knows the configuration. `Application\Code\SqidCodec` implements the format:

```php
use Phil\ShortLinkBundle\Application\Code\SqidCodec;

$codec = new SqidCodec(minLength: 6);
$codec->encode([1, 2, 3]); // "86Rf07"
$codec->decode('86Rf07');  // [1, 2, 3]
```

An identifier is no secret: it hides the order of the numbers, nothing more. The number of a drawn code is random, so a
code tells nothing of the links created before or after it, and the next one cannot be guessed.

## The alphabet

The characters of the codes, each once, among those a URL path carries without escaping: letters, digits, `-`, `.`,
`_` and `~`, at least three of them. A different alphabet gives different codes: an application that shuffles the
default alphabet once, and keeps it, has codes of its own.

## The blocklist

A drawn code avoids the words of the blocklist: one that contains a word, starts or ends with a word that has a digit,
or is a word of three characters, is replaced by the next identifier the format gives. A word is kept only when it has
three characters or more, all in the alphabet; it is compared in lower case. The default list holds rude words in
English and French; `code.blocklist` adds others, and `code.default_blocklist: false` drops the default list.

## Drawn and chosen codes

A code left empty is drawn: a random number from 0 to `code.max_number`, written with at least `code.min_length`
characters. A code already taken is drawn again, eight times at most; then the creation is refused, and a larger
`max_number` gives more room.

When `code.custom` allows it, an author may choose a code: between 3 and 64 characters of the alphabet, no word of the
blocklist, and one nobody holds. A code is never changed once the link exists, and never reused while the link is
there.

## Decoding

`SqidCodec::decode()` is bounded: an identifier longer than 255 characters, with a character out of the alphabet, or
whose number would not fit in a PHP integer decodes to nothing. `isCanonical()` tells whether an identifier is the one
the codec writes for its numbers: several identifiers can decode to the same numbers, and only one is written.

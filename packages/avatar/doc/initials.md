# Initials

`Initials::of($name, $max)` takes the initials of a name; the initials style calls it with the name and `maxLetters` of
the options.

## The rules

1. The name is cut into words at spaces, punctuation and symbols, of every script: a hyphen, an apostrophe, a Hebrew
   maqaf, a no-break space. Emoji and other symbols are left out.
2. Each word gives its first grapheme cluster that starts with a letter or a digit: the letter with its combining marks,
   a whole Hangul syllable, a Devanagari consonant with its vowel sign.
3. That letter is uppercased one for one: `é` gives `É`, `ǆ` gives `Ǆ`, `ß` stays `ß` rather than becoming two letters.
4. The first words and the last one give the initials: two letters take the first and last words, three the first two
   and the last; one letter takes the first word.
5. A name whose first letter is Han, Hiragana, Katakana or Hangul gives that character alone: these scripts write a name
   without spaces, and one character fills the space of two letters.
6. Initials in Arabic, Hebrew, Syriac, Thaana, N'Ko, Samaritan, Mandaic or Adlam are right to left: `isRightToLeft()`
   says so, and the style lays them out that way.

| Name | Two letters | Three letters |
|---|---|---|
| Ada Lovelace | AL | AL |
| Jean-Luc de la Tour | JT | JLT |
| Mary O'Brien | MB | MB |
| émile zola | ÉZ | ÉZ |
| 山田太郎 | 山 | 山 |
| 김민준 | 김 | 김 |
| محمد علي | مع, right to left | مع, right to left |
| 😀 🎉 | none | none |

## Names without initials

A name that is empty, made of spaces, punctuation or emoji only, or not valid UTF-8, gives no initials: `isEmpty()` is
true, and the initials style draws the shapes style instead. A name longer than 1 KiB is read from its first kilobyte,
cut on a character boundary.

The extraction never throws on a name, whatever its bytes; a fuzz target holds it to that, and to initials of three
grapheme clusters at most, each starting with a letter or a digit.

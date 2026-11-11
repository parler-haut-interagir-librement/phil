# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\Slug\Slug` | `final readonly class` | One URL segment, checked |
| `Phil\Slug\InvalidSlug` | `InvalidArgumentException` | A text that is not a slug, or one no slug can be made of |
| `Phil\Slug\Slugger` | `interface` | Turns a text into a `Slug` |
| `Phil\Slug\TransliteratingSlugger` | `final readonly class`, `Slugger` | Transliterates to ASCII, cuts at a word |
| `Phil\Slug\LeadingWordsRemoved` | `final readonly class`, `Slugger` | Decorator: drops one leading word of a list |
| `Phil\Slug\Sluggers` | `final readonly class` | The named sluggers, and the default one |
| `Phil\Slug\UnknownSlugger` | `OutOfBoundsException` | No slugger has that name |

## `Slug`

| Rule | Value |
|---|---|
| Characters | lowercase ASCII letters and digits |
| Words | joined by single hyphens; none leading or trailing |
| Length | 1 to 255 characters (`Slug::MAX_LENGTH`) |

`Slug::fromString()` checks and **never fixes**: `My-Article` beside `my-article` would be two addresses for one
page, so a text that is not already a slug is refused with `InvalidSlug`. `equals()` compares the spelling;
`toString()` and `(string)` give it back.

## `Slugger`

```php
interface Slugger
{
    /** @throws InvalidSlug when the text holds no letter or digit a slug can be made of */
    public function slug(string $text): Slug;
}
```

### `TransliteratingSlugger`

```php
new TransliteratingSlugger(?string $locale = null, int $maxLength = Slug::MAX_LENGTH);
```

| Text | Slug |
|---|---|
| `Jeux de rôle` | `jeux-de-role` |
| `L'Ordre du Corbeau` | `l-ordre-du-corbeau` |
| `Ça va? Œuvre — été` | `ca-va-oeuvre-ete` |
| `Straße über`, locale `de` | `strasse-ueber` |

- The transliteration is that of `symfony/string`'s `AsciiSlugger`, in the locale when one is given.
- A slug longer than `$maxLength` is **cut at the last hyphen that fits**, so no word is cut in two; a first word
  longer than the maximum is cut where the maximum falls.
- `$maxLength` is 1 to 255; anything else is refused with `InvalidSlug`.
- A text with no letter or digit (`' ?! '`) is refused with `InvalidSlug`.

### Decorating a slugger

A slugger is changed by decorating it: a class implements `Slugger`, receives the slugger it wraps, calls it, and
changes the text before or the slug after.

`LeadingWordsRemoved` is one:

```php
new LeadingWordsRemoved(Slugger $slugger, list<string> $words);
```

- It drops **one** leading word, **once**, when it is in the list and other words follow it: with
  `['le', 'la', 'les', 'l', 'the']`, `Les Gardiens` is `gardiens`, `The Guild` is `guild`, and `Les` stays `les`.
- The words are spelled as slug words — `L'Ordre` gives `l-ordre`, whose first word is `l` —; a word that is not one
  run of lowercase ASCII letters and digits is refused with `InvalidSlug`. `LeadingWordsRemoved::checkWord($word)`
  applies the same rule without building a slugger, for a configuration to check its words early.
- It is never applied by default: an application chooses the sluggers that drop words, and which words.

## `Sluggers`

```php
new Sluggers(array<string, Slugger> $sluggers, string $default);

$sluggers->get(?string $name = null): Slugger;          // the default one without a name
$sluggers->slug(string $text, ?string $name = null): Slug;
$sluggers->names(): list<string>;
```

An unknown name — given to `get()` or `slug()`, or as the default — is refused with `UnknownSlugger`, whose message
lists the names there are.

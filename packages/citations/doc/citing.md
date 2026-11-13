# Citing

## Citations

A `Citation` is the references cited together at one place of a text: one parenthesis, one note, one bracket. Each is
a `Cite`: an id, a locator and its kind (`Locator::Page` by default, or `Chapter`, `Section`, `Paragraph`, `Volume`,
`Figure`, `Line`, `Note`), and words before or after it.

```php
use Phil\Citations\Citation;
use Phil\Citations\Cite;
use Phil\Citations\Locator;

$citations = [
    new Citation(new Cite('smith2020', '47'), new Cite('knuth1984', '12-14')),
    new Citation(new Cite('roy2015', '3', Locator::Chapter, prefix: 'see')),
    Citation::of('smith2020'),
];
```

## The processor

```php
use Phil\Citations\CitationProcessor;
use Phil\Citations\Style\Notes;
use Phil\Citations\Terms;

$rendered = new CitationProcessor(new Notes(), Terms::french())->render($references, $citations, uncited: false);

$rendered->citations;     // list<RichText>, one per citation, in order
$rendered->bibliography;  // list<RichText>, in the style's order
```

The processor resolves each id through the `ReferenceList`, a duplicate to the reference it duplicates, and leaves a
cite of the same reference twice in one citation at one. It orders the bibliography alphabetically — by creators, year,
title, in the collation of the language — or by first citation, as the style asks, numbers the entries, gives the year
letters when the style wants them, and tells a first cite from a later one. With `uncited: true` the bibliography
lists the references never cited too. It keeps nothing between two calls.

## The styles

| Style | In the text | In the bibliography |
|---|---|---|
| `Style\AuthorDate` | « (Smith et al., 2020, p. 47; Knuth, 1984) », the works of one author grouped (« (Lee, 2018a, 2018b) ») | « Smith, J. A., Dupont, J.-P., & World Health Organization. (2020). On Testing Café. *Journal of Tests*, *12*(3), 45–67. `https://doi.org/10.1000/xyz` » |
| `Style\Notes` | The text of a footnote: full the first time, « Knuth, *The TeXbook*, 14. » after | « Knuth, Donald E. *The TeXbook*. 2nd ed. Reading, MA: Addison-Wesley, 1984. » |
| `Style\Numeric` | « [1, p. 47], [2] », « [3]–[5] » for three or more in a row | « [1] J. A. Smith, J.-P. Dupont, and World Health Organization, “On Testing Café,” *Journal of Tests*, vol. 12, no. 3, pp. 45–67, May 2020, doi: 10.1000/xyz. » |

The author-date style names one or two authors in a citation, three or more as the first and « et al. », up to twenty
in an entry; works of the same authors and year take a letter, works without a date read « n.d. ». The notes style
names up to three authors in a note and ten in an entry, writes a page locator bare, and quotes the title of a web page;
the note's number and its place belong to the text. The numeric style names up to six authors.

The styles follow the shape of these manuals for the common kinds of documents — articles, books, chapters and papers,
edited books, theses, reports, web pages — and are not a complete implementation of any of them.

## Terms

`Terms` holds the words and the typography of a language: `and`, `et al.`, the abbreviations of editor, edition, page
and volume, the quotation marks and whether punctuation goes inside them, the colon, the months, the ordinals of
editions, the labels of locators, the mark of years before the common era. `Terms::english()` and `Terms::french()`
are held; `Terms::forLocale('fr_CA')` picks one by language and throws `MissingTerms` for another, which is a `Terms`
built with its own words.

French terms write « dir. », « s. d. », « 2e éd. », guillemets with no-break spaces, a no-break space before the colon,
and « 375 av. J.-C. ».

## Text and HTML

A `RichText` is runs of text, some in italics. `toText()` gives the plain text; `toHtml()` gives HTML with every
character escaped and the italics in `<i>`, safe to write in a page as it is. No link is written: a DOI or a URL is
text.

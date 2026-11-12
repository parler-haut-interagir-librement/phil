# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `ext-ctype` | The subtags of a language tag told apart, letters or digits |
| `ext-dom` and `ext-libxml` | XLIFF, TMX and TBX written and read on PHP's DOM |
| `ext-mbstring` | Offsets, lengths and distances counted in code points |
| `phil/ujo` | The documents cut into segments and put back together |
| `phil/text-diff` | What changed in the source of a stale segment |
| `phil/fonts` | The character map of a template's font, for the check of the characters it draws |

## Installing

```sh
composer require phil/translation-memory
```

It is a library, not a bundle: there is nothing to register. Its services hold no state between two calls — build them
once and keep them:

```php
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;

$segmenter = new UjoSegmenter(Granularity::Block);
$memory = new TranslationMemory(new InMemoryStore(), minimum: 0.7, limit: 5);
```

The memory needs a store: `InMemoryStore` for tests and small memories loaded from a file, or an implementation of
`MemoryStore` over the application's database. In a Symfony application, `phil/translation-bundle` provides a Doctrine
store, keeps the segments and wires everything as services.

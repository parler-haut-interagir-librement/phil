# Memory and glossaries

## The memory

The bundle keeps one translation memory for the whole application, in the table `phil_translation_memory`: one unit
per pair of languages and source fingerprint, the latest translation winning. A regional variant reads the units of
its parent after its own. Every target that becomes usable —
translated, reviewed, confirmed, or imported as such — is learnt, its origin the document. The memory then proposes
it:

- to the new segments of any document, as `suggested`, when the source is the same, white space aside (`prefill`);
- on the screen, for any segment not yet usable, exact or close — down to `min_score`, at most `max_suggestions`.

With a `policy`, both proposals keep only the units learnt from a document the current user may translate
(`canTranslate()` on the unit's origin and the target language), and those learnt from no document — filled by the
application with `learn()` without an origin: one document's translation never shows on the screen of a user who may
not see that document. An application that gives other origins to the units it learns answers for them in its policy
too.

`Phil\TranslationMemory\Memory\TranslationMemory` is a public service: an application can look up the memory itself,
or fill it from an existing translation memory with `learn()`, or with its store, the alias `MemoryStore`.

## Glossaries

`Phil\TranslationBundle\Application\Glossaries` keeps one glossary per pair of languages, in the table
`phil_translation_term`:

```php
$glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie', 'PV'], forbidden: ['vie'], note: 'Always plural.'));
$glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('Dungeon Master'));   // kept as it is
$glossaries->remove(LanguageTag::of('en'), LanguageTag::of('fr'), 'hit points');
```

`put()` adds a term, or gives a term the glossary has its new translations, case, note and forms; `remove()` throws
`NotFound` for a term it does not have; `export()` and `import()` exchange the glossary as TBX. A regional variant has
glossaries of its own — `en` to `fr-CA` —, which the glossary pages edit like any other
([Glossary pages](glossary-pages.md)). `terms()` lists the rows of a pair of languages in alphabetical order, and
`glossary()` gives the library's `Glossary`, which the screen uses to show each segment's terms and its target's
issues. The glossary documentation of `phil/translation-memory` says how terms match.

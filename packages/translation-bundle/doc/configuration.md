# Configuration

```yaml
# config/packages/phil_translation.yaml
phil_translation:
    store: doctrine            # or "memory", for tests
    entity_manager: default
    granularity: block         # or "sentence"
    prefill: true
    min_score: 0.7
    max_suggestions: 5
    sources: ~                 # a SourceDocuments service
    policy: ~                  # a TranslationPolicy service
    layout: '@PhilTranslation/layout.html.twig'
    variants: [fr-CA, en-CA]   # the regional variants that inherit from their parent
    translators: ~             # a Translators service
    inflector: ~               # an Inflector service, such as Phil\TranslationMemory\Glossary\RegularPlurals
    machine_translation:
        enabled: false
        translator: ~          # a MachineTranslator service
```

| Option | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | Where segments, terms and the memory are kept: Doctrine, or PHP arrays that last one request — for tests |
| `entity_manager` | `default` | The Doctrine entity manager of the tables |
| `granularity` | `block` | How documents are cut: one segment per text field of a block, or one per sentence |
| `prefill` | `true` | Whether new segments are proposed the exact matches of the memory, as `suggested` |
| `min_score` | `0.7` | The lowest score of a fuzzy match, from 0.01 to 1; 1 keeps exact matches only |
| `max_suggestions` | `5` | The most matches proposed for a segment, at least 1 |
| `sources` | none | The service, a `SourceDocuments`, that finds the documents to translate; the screen needs it |
| `policy` | none | The service, a `TranslationPolicy`, that says who may translate and review; the screen needs it |
| `layout` | `@PhilTranslation/layout.html.twig` | The Twig layout the screen and the glossary pages extend; it defines the blocks `title` and `content` |
| `variants` | `[fr-CA, en-CA]` | The regional variants, as BCP 47 tags with a parent, that inherit the targets of their parent language; see [Regional variants](variants.md) |
| `translators` | none | The service, a `Translators`, that names the current user and the public credit of each translator; see [Comments and credits](collaboration.md) |
| `inflector` | none | The service, an `Inflector`, whose forms the glossaries add to their terms |
| `machine_translation.enabled` | `false` | Whether machine translation is on; see [Machine translation](machine-translation.md) |
| `machine_translation.translator` | none | The service, a `MachineTranslator`, an adapter named after its service; needed when `enabled` is true |

Changing `granularity` on documents already prepared changes every key: their segments are prepared again, the old
ones removed and the new ones proposed what the memory learnt from the old.

## Services

| Service | Public | Role |
|---|---|---|
| `Phil\TranslationBundle\Application\Translations` | yes | The translation of documents |
| `Phil\TranslationBundle\Application\Glossaries` | yes | The glossaries |
| `Phil\TranslationBundle\Application\Comments` | yes | The comments on segments |
| `Phil\TranslationMemory\Memory\TranslationMemory` | yes | The memory, for lookups of the application's own |
| `Phil\TranslationBundle\Infrastructure\Http\TranslationController` | yes | The screen |
| `Phil\TranslationBundle\Infrastructure\Http\GlossaryController` | yes | The glossary pages |
| `Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension` | no | The Twig function `phil_translation_credits()` |
| `Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository` | alias | The store of comments |
| `Phil\TranslationMemory\Variants` | no | The regional variants of the configuration |
| `Phil\TranslationMemory\Glossary\RegularPlurals` | no | The regular plurals of English and French, for `inflector` |
| `Phil\TranslationBundle\Domain\Repository\SegmentRepository` | alias | The store of segments |
| `Phil\TranslationBundle\Domain\Repository\TermRepository` | alias | The store of terms |
| `Phil\TranslationMemory\Memory\MemoryStore` | alias | The store of the memory |
| `Phil\TranslationMemory\Segmentation\UjoSegmenter` | no | The segmenter of the configured granularity |

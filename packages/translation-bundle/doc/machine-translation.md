# Machine translation

Machine translation proposes targets for the untranslated segments of a document. It is off by default, and its
targets are never more than proposals: each is `suggested`, marked as machine output on the screen, never learnt by the
memory until a person translates or reviews it, and credits nobody.

## The port

A machine translation service is reached through an adapter named after it, which implements
`Phil\TranslationBundle\Application\Contract\MachineTranslator`:

| Method | Contract |
|---|---|
| `name(): string` | The name of the service, shown in errors |
| `translate(list<string> $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): list<string>` | One target per source, inline HTML, in the same order |

The bundle ships no adapter and no SDK: the application writes the adapter of the service it chose, on Symfony's
HTTP client, and names it in the configuration.

```yaml
phil_translation:
    machine_translation:
        enabled: true
        translator: App\Translation\SomeServiceTranslator
```

`enabled: true` without a `translator` stops the container with a message; a `translator` without `enabled: true`
stays off.

## Translating

```php
$count = $translations->machineTranslate('article:42', LanguageTag::of('fr'));
```

The segments of the prepared document that are untranslated are sent by batches of 50
(`Translations::MACHINE_BATCH`). Each target with words, in valid UTF-8, is proposed — `suggested` — and the row marked
as machine output (`SegmentRecord::isMachine()`); a target without words is left out. The method gives how many
segments were proposed a target.

`MachineTranslationFailed` is thrown when machine translation is off, when the translator throws — its exception kept
as the previous one —, or when it does not answer one target per source; nothing is written then. `NotFound` is thrown
for a document not prepared in the language. `canMachineTranslate()` tells whether a translator is configured.

## On the screen

When a translator is configured, the page shows a button that proposes machine translations for the untranslated
segments (`POST /{document}/{language}/machine`), then reports how many were proposed. Each target that came from the
machine and that nobody has rewritten carries the mark `machine translation`. A failure of the service answers `502`
with a message of its own, the same whatever the service answered: what an adapter's exception says — an address, a
quota, a key — stays out of the page, and stays in the `MachineTranslationFailed` it chains, for the application's
logs. Without a translator, the route answers `404`.

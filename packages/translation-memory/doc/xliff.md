# XLIFF

Segments leave for a translation tool as an XLIFF file and come back from one.

## Export: XLIFF 2.1

```php
$file = XliffFile::fromSegments('page:42', LanguageTag::of('en'), LanguageTag::of('fr'), $segments);
$xml = new XliffWriter()->write($file);
```

```xml
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en" trgLang="fr">
  <file id="f1" original="page:42">
    <unit id="u1" name="b1/text">
      <originalData><data id="d1">&lt;b&gt;</data><data id="d2">&lt;/b&gt;</data></originalData>
      <segment state="translated">
        <source>A <ph id="1" dataRef="d1"/>bold<ph id="2" dataRef="d2"/> word</source>
        <target>Un mot <ph id="1" dataRef="d1"/>gras<ph id="2" dataRef="d2"/></target>
      </segment>
    </unit>
  </file>
</xliff>
```

(The writer puts no white space between elements; it is added here to read.)

- One unit per segment, identified `u1`, `u2`… — XLIFF identifiers are tokens, keys are not — and named after the key.
- Tags become `<ph>` codes whose HTML is in the unit's `<originalData>`: a translation tool shows them as codes to place.
  A tag of the target that the source holds takes the same code.
- Text is written as text: `&amp;` in the source is `&` in the file, escaped by XML.

| Segment | `state` | `subState` |
|---|---|---|
| untranslated | `initial` | — |
| suggested | `initial` | `phil:suggested` |
| translated | `translated` | — |
| reviewed | `reviewed` | — |
| stale | `initial` | `phil:stale`, and the earlier source in `<note category="phil:previous-source">` |

## Import: XLIFF 2.x and 1.2

```php
$file = new XliffReader()->read($xml);
$result = XliffMerge::merge($segments, $file, LanguageTag::of('en'), LanguageTag::of('fr'));
```

`XliffReader` reads XLIFF 2.0, 2.1 and 2.2 — namespace `urn:oasis:names:tc:xliff:document:2.0` — and XLIFF 1.2 —
namespace `urn:oasis:names:tc:xliff:document:1.2`. A file that declares a document type is refused before it is parsed;
nothing is fetched from the network.

| | XLIFF 2.x | XLIFF 1.2 |
|---|---|---|
| Key | `name`, else `id` of the unit | `resname`, else `id` of the `trans-unit` |
| Languages | `srcLang`, `trgLang` of the root | `source-language`, `target-language` of the first `<file>` |
| Codes turned back into HTML | `<ph>`, `<sc>`, `<ec>` by `dataRef`; `<pc>` by `dataRefStart` and `dataRefEnd`; `<cp>` as its character | The native code inside `<ph>`, `<bpt>`, `<ept>`, `<it>` |
| Codes kept as their words only | `<mrk>`, `<pc>` without data | `<g>`, `<mrk>` |
| Several segments | Joined, with the `<ignorable>` between them; the least advanced state | — |

| State in the file | Segment |
|---|---|
| no target, or a target without words | untranslated |
| 2.x `initial`; 1.2 `new`, `needs-*` or a custom state | suggested |
| 2.x `translated`; 1.2 `translated` or no state | translated |
| 2.x `reviewed` or `final`; 1.2 `final`, `signed-off` or `approved="yes"` | reviewed |

## Merging

`XliffMerge::merge()` checks the languages given against the file's, then applies each unit to the segment of its key:

| Skipped as | When |
|---|---|
| `unknown-key` | No segment has the key |
| `source-changed` | The segment's source no longer has the words the file was made from |
| `no-target` | The unit has no target with words |
| `unchanged` | The target and state are those of the segment, or the same target only proposed |

Other units are applied as suggested, translated or reviewed, as the file says. Tags the source does not hold are left
out of the target, their words kept: a file cannot bring markup the document never had. The `MergeResult` holds every
segment in its order, the keys applied and the units skipped.

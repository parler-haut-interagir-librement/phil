# Testing

| Area | What is covered |
|---|---|
| `StoredDate`, `Definitions`, `ConfiguredDates`, `AlmanacFactory` | A definition kept and given back, changed; stored dates replacing configured ones; the almanac seeing a date at once, in its time zone |
| `DateDraft` | A draft of a definition and back; each refusal, on its field, and the whole date checked only once its fields are valid |
| `TagsTransformer`, `AlmanacExtension`, `SkyExtension`, `Places` | Tags read and written; the dates of a period in Twig; the solar day of a place by name or as a `Place`, text read in its time zone; the eclipses of a period, of one body or both, and an unknown body refused; an unknown place named with those there are |
| `PhilAlmanacBundle` | The services with and without the database, configured dates built as definitions, the declared eclipses and a date that replaces one, the places and their default time zone, Doctrine found in a kernel's bundles, every configuration refused |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle: stored
dates saved, read, replacing a configured one and removed; `almanac_dates` rendered, the declared eclipses among them;
`sun_times` and `almanac_eclipses` rendered; the form submitted, refused in French and rendered. Installed on its own,
where FrameworkBundle is a development requirement, it is skipped.

```sh
vendor/bin/phpunit packages/almanac-bundle/tests
```

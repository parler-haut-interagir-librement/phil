# CAP alerts

`Cap\CapReader` reads a Common Alerting Protocol 1.2 document (`urn:oasis:names:tc:emergency:cap:1.2`) and returns one
`WeatherAlert` per `<info>` block:

| `WeatherAlert` | From |
|---|---|
| `id` | The alert's `identifier` and the block's `language` |
| `event`, `headline`, `description`, `instruction` | The block's elements, as written |
| `severity` | The block's `severity`, `Unknown` when it is not one of the five CAP values |
| `sender` | The block's `senderName`, else the alert's `sender` |
| `effective` | The block's `onset`, else its `effective`, else the alert's `sent` |
| `expires` | The block's `expires` |
| `area` | The `areaDesc` of each `<area>`, joined by a semicolon and a space |
| `language` | The block's `language`, `en-US` when it has none, as CAP says |
| `web` | The block's `web` when it is an `http` or `https` address |

```php
use Phil\EventWeather\Cap\CapReader;

$alerts = new CapReader(language: 'fr')->read($xml); // the fr-CA blocks
```

The language asked for takes its regional forms: `fr` reads `fr-CA` and `fr-FR`. Without a language, every block is
read. The texts are those of the block in that language, as written: a page shows them as they are, never summarized
nor machine-translated — see [Showing alerts](licences.md#showing-alerts).

Only an actual alert gives alerts: a document whose `status` is `Test`, `Exercise`, `System` or `Draft`, or whose
`msgType` is `Cancel`, returns none. An `Update` is read as the alert it replaces.

The reader refuses with `Exception\InvalidCapDocument` a document that is not UTF-8 or holds a control character, has a
document type, is not XML, is not a CAP 1.2 alert, lacks its `identifier`, `sender`, `sent`, `status` or `msgType`, or
an `<info>` without its `event`. It is bounded by `$maxBytes`, 1 MiB by default, and `$maxInfos`, 64 blocks.

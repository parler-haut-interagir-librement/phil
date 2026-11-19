# Licences and attribution

Both providers publish open data under licences that allow commercial use and require attribution. Each `Forecast`
carries the `Attribution` of its provider, and each `EventForecast` the attributions of every provider whose data it
holds: show them beside the data, with a link to the licence.

## Environment and Climate Change Canada

| | |
|---|---|
| Data | City page forecasts and public weather alerts, from the MSC GeoMet OGC API (`api.weather.gc.ca`) |
| Licence | Environment and Climate Change Canada Data Servers End-use Licence — <https://eccc-msc.github.io/open-data/licence/readme_en/> |
| Use | Worldwide, royalty-free, perpetual and non-exclusive, commercial use included |
| Attribution | "Data Source: Environment and Climate Change Canada", or, when several attributions are impractical, "Contains information licenced under the Data Server End-use Licence of Environment and Climate Change Canada" |
| Conditions | No use that suggests official status or endorsement; watches and warnings reproduced without altering their content or intent; the data is provided as is |
| Usage policy | <https://eccc-msc.github.io/open-data/usage-policy/readme_en/>: a meaningful `User-Agent`, only the data used, about one request per second at most without talking to the MSC first |

`Attribution::environmentCanada()` gives the credit line `Data Source: Environment and Climate Change Canada`, the
licence and its address, and `https://weather.gc.ca/` as the source.

The library keeps the texts of an alert as issued and never shortens them; a page that shows an alert follows the
rule below.

## Showing alerts

**An alert is never summarized nor machine-translated: its text is shown as issued, in the language the source gives.**

The licence of Environment and Climate Change Canada asks that watches and warnings be reproduced without altering
their content or intent (see its conditions, <https://eccc-msc.github.io/open-data/licence/readme_en/>). A summary
drops what the forecaster chose to say; a machine translation may change a warning's meaning, its tone or its
instruction, the very parts people act on. So a site that shows a `WeatherAlert`:

- shows its `event`, `headline`, `description` and `instruction` as they are, without shortening, rewording or
  rewriting them through a language model, and with their `severity`, `effective` and `expires`;
- shows the alert in the language its source issued it in, its `language`. Environment Canada issues each alert in
  English and in French: ask `CapReader` or the alert source for the language of the page, and show the other
  language's text, as issued, when the page's language is neither;
- may lay out, style and order alerts, show its own label beside one — a badge of its severity, "Weather alert" in the
  page's language —, and link to the alert's `web` page or to <https://weather.gc.ca/>, as long as the label stays
  apart from the alert's text and does not stand for it;
- never suggests that the alert, or the page, is endorsed by Environment and Climate Change Canada, and shows the
  attribution beside it.

The same rule holds for any alert source an application adds: an official warning is quoted, never paraphrased.

## MET Norway

| | |
|---|---|
| Data | Locationforecast 2.0 (`api.met.no`) |
| Licence | Norwegian Licence for Open Government Data 2.0 (<https://data.norge.no/nlod/en/2.0/>) and Creative Commons Attribution 4.0 International (<https://creativecommons.org/licenses/by/4.0/>) — <https://api.met.no/doc/License> |
| Attribution | Credit MET Norway as the source, such as "Data from MET Norway", with a link to the source where possible |
| Conditions | No suggestion of endorsement; the name "Yr" and its logo are not used; the weather icons are a separate work under the MIT licence |
| Terms of service | <https://api.met.no/doc/TermsOfService>: an identifying `User-Agent` with a contact, never a made-up one; no new request before an answer's `Expires`, `If-Modified-Since` after it; under 20 requests per second; coordinates cut to four decimals; HTTPS only |

`Attribution::metNorway()` gives the credit line `Data from MET Norway`, the licences, the address of CC BY 4.0, and
`https://api.met.no/` as the source.

The symbol codes of Locationforecast are read into a `Condition` and kept in the period's `summary`; the library ships
no icon. An application that shows MET Norway's weather icons follows their own licence.

## What the library does for the terms

| Term | How |
|---|---|
| Identification | The `Contact` the application gives is the `User-Agent` of every request |
| `Expires` and `If-Modified-Since` | Through the `CachingHttpClient` the application wraps its client in — see [Installation](installation.md) |
| Four decimals | `MetNorway\LocationforecastProvider` cuts the coordinates before asking |
| Only the data used | One request per forecast, around the event; the alerts of the event's place only |
| HTTPS | Both endpoints are `https` addresses |

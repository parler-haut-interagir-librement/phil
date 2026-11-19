# Security

## The requests

The adapters ask fixed `https` endpoints of their providers, or the endpoint the application configures, a
`Phil\Url\Url`; nothing a visitor types reaches an address. The query carries only the event's coordinates — cut to
four decimals for MET Norway, as a box around them for Environment Canada — and never the event, its organiser or its
visitors. The `User-Agent` is the application's `Contact`, checked to be one line: a name of letters, digits and
`._-/+`, and a web or email address.

Every request is bounded: 10 seconds of silence, 20 seconds in all, three redirects, 8 MiB of answer read in chunks.
The providers are trusted services, not addresses from members: the library does not run their answers through an
address guard, and the application gives it an HTTP client without a proxy it does not trust.

## The answers

A provider's JSON is decoded with its nesting bounded to 64 levels, and read without trusting its shape: a missing key
or a value of another type is null, an instant without its offset is not read, a percentage or a direction out of
range is clamped or dropped. A provider that sends what its format does not describe throws
`Exception\WeatherUnavailable`, which `EventWeather` logs and turns into an unavailable forecast.

The CAP reader refuses a document type — the way entity expansion attacks arrive —, reads without the network, refuses
what is not UTF-8 or holds a control character, and bounds the bytes and the `<info>` blocks. It is fuzzed — see
[Testing](testing.md).

## The texts

The summaries of periods and the texts of alerts are the providers' words, kept as they came: plain text the
application escapes when it renders it, as Twig does by default. A `WeatherAlert`'s `web` is an `http` or `https`
address read by `phil/url`, or null.

## What stays with the application

- The cache that keeps the answers between requests, and its storage.
- The rate of requests: a page that shows many events in one place shares one cached answer; an application that
  forecasts thousands of places a minute talks to the providers first.
- Escaping the providers' texts in its pages, and showing the attributions.

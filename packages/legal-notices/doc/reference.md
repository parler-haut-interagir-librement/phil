# Reference

Every type lives in `Phil\LegalNotices`; the ports in `Contract`, the exception in `Exception`, the event in `Event`.
A value refused throws `InvalidLegalNotice`, whose `reason` is a short stable code and whose message says the same for
a person, quoting a value cut to its first 80 characters.

## Documents and fields

`DocumentType` has two cases: `LegalNotice` (`legal_notice`) and `PrivacyPolicy` (`privacy_policy`).
`requiredFields()` lists the fields each one cannot be published without:

| Document | Required fields |
|---|---|
| `legal_notice` | `publisher_name`, `publisher_address`, `publication_director`, `contact_email`, `host_name`, `host_address`, `host_contact` |
| `privacy_policy` | `publisher_name`, `contact_email`, `dpo_name`, `dpo_contact` |

`Field` has the other fields too: `site_name`, `publisher_form` (the legal form), `publisher_registration` (a business
or company number) and `contact_phone`.

## SiteIdentity

`new SiteIdentity(array $values)` takes the values by field name. A value is trimmed, its line breaks made `\n`; an
empty value or `null` is a field left out. A value is UTF-8 of 1,000 bytes at most without control characters, on one
line but for `publisher_address` and `host_address`; `contact_email` is an e-mail address. An unknown field is refused.

| Method | Returns |
|---|---|
| `get(Field $field)` | The value, or `null` |
| `values()` | The values given, by field name |
| `missing(DocumentType $type)` | The required fields the identity lacks, in the order of the document |

## LegalDocumentVersion

```php
new LegalDocumentVersion(
    DocumentType $type,
    int $number,                         // 1 to 999999
    array $bodies,                       // by locale, one at least, 256 KiB each at most
    string $format,                      // "text", "markdown", "ujo"…: a lowercase name of 32 characters at most
    DateTimeImmutable $effectiveAt,
    PublicationState $state = PublicationState::Draft,
    ?DateTimeImmutable $publishedAt = null,
    array $summaries = [],               // by locale, 1,000 bytes each at most
);
```

A draft has no publication date; a published or withdrawn version has one, and takes effect then or later. A locale is
`fr` or `fr_CA` — a `-` is read as `_` —, 32 at most; each body and summary is trimmed and may not be empty.

| Method | Returns |
|---|---|
| `body($locale, $fallback)` | The body in the locale, else in its language, else in the fallback, else in the first locale |
| `summary($locale, $fallback)` | The summary found the same way, or `null` |
| `locales()` | The locales of the bodies |
| `isPublic()` | Whether the version is published or withdrawn |
| `isInForceAt($at)` | Whether it is published and its effective date has come |
| `publish($at)` | The draft published at a moment that is not after its effective date |
| `withdraw()` | The published version withdrawn |

## PublicationState

| State | Shown | In force |
|---|---|---|
| `Draft` | never | never |
| `Published` | in the history and at its address | from its effective date, until a later one takes effect |
| `Withdrawn` | in the history and at its address, marked withdrawn | never |

## DocumentHistory

`new DocumentHistory(DocumentType $type, iterable $versions)` keeps the versions by number. A version of another
document, a number given twice and more than 1,000 versions are refused.

| Method | Returns |
|---|---|
| `versions` | Every version, the oldest number first |
| `inForce($at)` | The published version that took effect last at that moment, the highest number on the same date |
| `upcoming($at)` | The published version that takes effect next after that moment, the highest number on the same date |
| `publicVersions()` | The published and withdrawn versions, the newest first |
| `find($number)` | A published or withdrawn version by number, or `null` |

The version in force is the one that took effect last, whatever its number: a correction may take effect before a
version announced for later.

## Retention and consent

`RetentionDuration($id, $label, RetentionKind $kind, ?$duration, ?$note, $labels)`:

| Kind | Duration | Note |
|---|---|---|
| `Fixed` | an ISO 8601 duration, required | ignored |
| `UntilEvent` | none | the event, required |
| `Indefinite`, `NotYetFixed` | none | ignored |

The identifier is letters, digits, `.`, `_` and `-`, 64 characters at most; the label says what is kept, in 1 to 300
bytes; `label($locale)` gives the label of the locale or its language, else the default label.

`DurationWords::describe($retention, $locale)` gives the period in words, in French for a French locale and in English
otherwise: `P2Y6M` is "2 years and 6 months", "2 ans et 6 mois"; a period until an event is its note; the two others
are fixed phrases. A duration takes four digits at most per part, mixes weeks with the other parts, and may not be a
duration of nothing.

`ConsentCategory($key, $necessary, $labels, $descriptions, $cookies)` has a key of lowercase letters, digits and `_`, a
name in one locale at least, and 100 cookie names at most, each a cookie token where `*` stands for any end.

## LegalNotices

| Method | Returns |
|---|---|
| `history($type)` | The history the source gives; one of another document is refused |
| `inForce($type, $at)` | The version in force at a moment |
| `identity()` | The `SiteIdentity` |
| `render($version, $locale)` | The body as HTML, its placeholders filled — see [Placeholders](placeholders.md) |
| `retention()`, `consent()` | What the ports give, or nothing without them |
| `publishedBetween($from, $to)` | The published versions whose publication date falls in the span, both ends included |
| `check()` | The `Problem`s of the public documents |

`check()` reads each document that has a published or withdrawn version: first the required fields the identity lacks
(`missing_field`), then, for each public version and each of its locales, a body the renderer refuses
(`unreadable_body`), a placeholder without a value (`unknown_placeholder`) or a placeholder written outside the text,
in a tag, a comment or a script, which is never filled (`misplaced_placeholder`). A `Problem` carries the document,
the code, the message, and the version and locale when it is one of a body.

`DocumentPublished($type, $version)` carries the identifiers of a version just published, for a listener to react.

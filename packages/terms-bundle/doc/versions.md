# Policies and versions

## Policies

A policy is declared in the configuration by its key. A **required** policy is one a signed-in member must have
accepted to go on: the gate asks for it. A policy that is not required — a code of conduct read once, a policy shown
for information — has its pages and its acceptances, and the gate leaves it alone. One policy may name the licence of
the members' contributions; see [Contribution licence](contribution-licence.md).

## Versions

A version is published once and never changed: a correction is a new version.

| Part | Rule |
|---|---|
| Number | 1 for the first, then one more than the latest, up to 999999 |
| Texts | The text of each locale, 32 locales at most, each valid UTF-8 of 256 KiB at most and not blank |
| Format | `text` or `ujo` (UJO (Universal JSON Output), with `phil/ujo`), checked when published |
| Published at | The moment of publication, from the clock |
| Effective at | The moment it takes effect: at publication or later; a moment already past is the moment of publication |
| Material | Whether every member must accept it again; the first version always is |
| Licence | The SPDX identifier of the contribution licence: required on the policy that names it, refused on the others |

Both moments are written in UTC and read back in UTC, whatever the time zone of the clock or of the server: a moment
given in another time zone is kept as the instant it is.

```php
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\TextFormat;

$terms->publish('privacy', ['en' => $english, 'fr' => $french]);                       // material, in force now
$terms->publish('privacy', ['en' => $fixed], material: false);                        // a correction, asks nothing
$terms->publish('privacy', ['en' => $json], TextFormat::Ujo, new DateTimeImmutable('2026-07-01'));  // announced ahead
```

A refusal is an `InvalidTerms` whose `reason` is a translation key of the `phil_terms` domain, prefixed by `error.`,
and whose `parameters` fill it. Two publications at once cannot both take a number: the unique index refuses the
second, with the reason `version_taken`.

## Three versions at a moment

At any moment, each policy has:

- the version **in force**: the newest that has taken effect — the one its page shows and a member accepts;
- the version **required**: the newest *material* version that has taken effect — a member who accepted it, or a later
  one, is in order;
- the **latest material** version, whether it has taken effect or not — the one a notice of publication is about.

A version published with a later moment is shown on the public pages as upcoming and may be accepted ahead; the gate
asks for it once it takes effect. A minor version takes the place of the version in force without asking anything of
those who accepted the required one; a member who accepts from then on accepts it.

## Texts

A plain text is shown escaped, its paragraphs — separated by a blank line — each a `<p>`, its other line breaks kept.
A UJO document is rendered by `phil/ujo`, which sanitizes what it renders. A request in a locale the version has no
text for gets the text of `default_locale`, else the first text of the version.

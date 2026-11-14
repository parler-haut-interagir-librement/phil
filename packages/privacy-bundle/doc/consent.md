# Consent to cookies

Declare the categories a site uses, and read the consent where it matters.

```yaml
phil_privacy:
    consent:
        version: '2026-11'
        categories:
            session: { necessary: true, labels: { en: Session, fr: Session } }
            analytics:
                labels: { en: Measurement, fr: Mesure d'audience }
                descriptions: { en: Counts visits, fr: Compte les visites }
            videos:
                labels: { en: Videos, fr: Vidéos }
```

What each category writes in the browser is declared in the cookie inventory, `config/privacy/cookies.yaml`
([cookie-inventory.md](cookie-inventory.md)): the banner, the page of the choices and the cookie policy are drawn from
it, and a functional test fails on a cookie it does not declare. A frame of another site waits for its category with
`privacy_embed()` ([third-party-frames.md](third-party-frames.md)).

```twig
{{ privacy_consent_banner() }}

{% if privacy_consent('analytics') %}
    <script src="/analytics.js" defer></script>
{% else %}
    <template data-privacy-consent="analytics"><script src="/analytics.js" defer></script></template>
{% endif %}
```

| Function | Returns |
|---|---|
| `privacy_consent(category)` | Whether the category may run. A necessary one always; an unknown one never. |
| `privacy_consent_pending()` | Whether the person still has to be asked. |
| `privacy_consent_banner()` | The banner, when they do, with what each category writes. |
| `privacy_cookie_policy()` | The cookie inventory by category, for the site's privacy policy. |
| `privacy_embed(url, category, options)` | A frame of another site, loaded once its category is granted. |

## The rules

- **Refused by default.** Without a choice, every category that is not necessary is refused, and nothing is written in
  the visitor's browser until they answer.
- **Refusing is as simple as accepting.** Both are one click on a button of the same form, with the same class, on the
  banner and on the page.
- **Withdrawing at any time.** The page `phil_privacy_consent` is open to everyone, signed in or not. Link to it from
  the footer.
- **The Global Privacy Control is respected.** A browser that sends `Sec-GPC: 1` has every optional category refused,
  whatever a cookie says, and its user is not asked. The page says so, and its boxes are disabled.
- **A choice counts for one version of the text.** Change `consent.version` and every earlier choice stops counting, and
  everyone is asked again.

## The cookie and the proof

The choice is kept in a cookie of the site (`consent.cookie_name`), for `consent.lifetime`: no `Domain`, `HttpOnly`,
`SameSite=Lax`, `Secure` on HTTPS. It holds a random identifier, the version, the categories granted, the date, and an
HMAC under a key derived from the application secret. A value that is malformed, altered or too long reads as no choice.

Each decision also leaves a dated proof in `phil_privacy_consent_proof`: the identifier of the choice, the version of
the text, what was granted, the date and whether the signal was present. It names no one and holds no address. Without a
database the proof is a log line. Remove old proofs with a retention rule on the store `phil_privacy_consent_proof`.

A page that reads the consent gets `Vary: Cookie, Sec-GPC`, so that a shared cache does not hand one person's page to
another.

## Without JavaScript, and with it

Every decision is a plain `POST` that comes back to the page with a `303`: `accept`, `refuse`, `save` with the boxes
ticked, or `grant` with one `category`, added to those already granted, which is the button of a blocked frame. Each is
protected by a stateless CSRF token, so that a visitor who is only asked about cookies gets no session.

The Stimulus controller `phil--privacy-bundle--consent` sends the same form in the background. The banner closes, the
event `phil--privacy-bundle--consent:changed` gives the categories in effect, and the `<template data-privacy-consent>`
of a newly granted category are put in the page. A consent withdrawn reloads the page, the only way to stop what a
script has started. A request that fails falls back on the plain form.

## Your own markup

Override `templates/bundles/PhilPrivacy/consent/banner.html.twig` and `consent/page.html.twig`, or give the pages your
layout with `pages.layout`. The texts are in the `phil_privacy` translation domain, in English and French. A replacement
keeps refusing as visible as accepting.

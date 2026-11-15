# Configuration

```yaml
phil_legal_notices:
    clock: clock                         # service id of the PSR-20 clock
    default_locale: en                   # the body shown when the locale of the request has none
    identity:                            # the fields of the site, by name
        site_name: Example
        publisher_name: Example Inc.
        publisher_form: Corporation
        publisher_address: "1 Main Street\nMontréal QC"
        publisher_registration: '1234567890'
        publication_director: Jo Smith
        contact_email: hello@example.com
        contact_phone: '+1 514 555 0100'
        host_name: Example Hosting
        host_address: 2 Server Road, Toronto ON
        host_contact: support@hosting.example
        dpo_name: Jo Smith
        dpo_contact: privacy@example.com
    source: null                         # service id of a DocumentSource, instead of the documents below
    documents:
        legal_notice:
            - number: 1
              format: markdown           # text by default
              state: published           # published by default; draft or withdrawn
              published_at: '2026-12-01'
              effective_at: null         # the publication date by default
              bodies: { en: '…' }
              files: { fr: '%kernel.project_dir%/legal/notice.fr.md' }
              summaries: { en: 'The host changed.' }
        privacy_policy: []
    retention:
        source: null                     # service id of a RetentionScheduleSource of phil/privacy-bundle
        personal_only: true              # only the rules that hold personal information
        labels: { R-001: { en: Your account, fr: Votre compte } }
    consent:
        source: null                     # service id of a ConsentCategories, instead of the categories below
        categories:
            necessary: { necessary: true, labels: { en: Necessary, fr: Nécessaires }, cookies: [PHPSESSID] }
            analytics:
                labels: { en: Audience measurement, fr: Mesure d'audience }
                descriptions: { en: Pages seen, without a profile. }
                cookies: ['_pk_id.*']
    markup: null                         # service id of the phil/markup Markup; one is built when it is installed
    pages:
        layout: '@PhilLegalNotices/layout.html.twig'
        retention_section: true          # the retention periods under the privacy policy
        consent_section: true            # the consent categories under the privacy policy
        kit_subdirectory: null           # where the recipes of the kit are; the subdirectory of ux_toolkit when null
    footer:
        terms_policies: []               # policies of phil/terms-bundle linked after the documents
    sitemap_section: default             # the section of phil/sitemap-bundle
```

## The versions

A version has a number, a format, a state, a publication date unless it is a draft, and an effective date — the
publication date when it is left out. A date is `2026-12-01` or `2026-12-01T09:00`, maybe with seconds and an offset,
read in UTC when it has none. Each body is given inline under `bodies` or as a file under `files`, never both for one
locale; a file is read when the container is built, 256 KiB at most, and a change to it rebuilds the container in
debug mode. The rules of a version are those of the reference of phil/legal-notices.

The values of the configuration are read by the container, which takes `%name%` for a parameter: the placeholders
of a body are `${name}`, which it leaves alone.

## The checks of the container build

The container build fails, naming the option, on:

- a field of the identity that is unknown or refused;
- a document other than `legal_notice` and `privacy_policy`, a version that breaks a rule, a date of another shape;
- a body given twice for a locale, a file that is missing, unreadable or larger than 256 KiB;
- a document with a published or withdrawn version whose identity lacks a field it requires;
- a consent category that breaks a rule;
- `source` together with `documents`, and `retention.source` without phil/privacy-bundle.

A `source` of the application is not read at build time: `phil:legal-notices:check` reports what its documents lack.

# Installation

## Requirements

- PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later and Twig, with the Twig bundle and the translator for the
  pages.
- Symfony UX Twig Components and the recipes `badge` and `table` of the PHIL UX kit, installed by the Symfony UX
  Toolkit, for the pages; twig/intl-extra writes their lists in the language of the page.
- phil/markup for bodies in Markdown, UJO (Universal JSON Output) or HTML; without it, bodies are plain text.
- phil/privacy-bundle for the retention periods of its schedule, phil/terms-bundle for its policies among the footer
  links, phil/sitemap-bundle for the pages in the sitemap, phil/structured-data-bundle for the pages in JSON-LD: each is
  optional.

## Installing

```sh
composer require phil/legal-notices-bundle
```

The Flex recipe of the bundle registers it and writes:

| File | What it holds |
|---|---|
| `config/packages/phil_legal_notices.yaml` | The fields of the identity, left empty; the legal notice and the privacy policy, each a draft version 1 whose bodies are files; the consent category of the session cookie |
| `config/routes/phil_legal_notices.yaml` | The routes, in English at the root and in French under `/fr` |
| `legal/notice.en.md`, `legal/notice.fr.md`, `legal/privacy.en.md`, `legal/privacy.fr.md` | The bodies, in Markdown, naming the fields of the identity as placeholders |

A draft is shown by no page and needs no field of the identity, so the application builds right after the install;
the pages answer 404 until a version is published. Without Flex, register the bundle and write these files yourself:

```php
// config/bundles.php
return [
    // …
    Phil\LegalNoticesBundle\PhilLegalNoticesBundle::class => ['all' => true],
];
```

## The recipes of the kit

The pages draw their badges and their table with recipes of the PHIL UX kit, copied into the application by the
Symfony UX Toolkit; `PhilLegalNoticesBundle::RECIPES` lists them:

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

A site on Tailwind installs the same recipes from the Shadcn kit of the Toolkit instead; the pages are tested with
both kits. See
[Pages](pages.md#the-recipes-of-the-kit).

## The routes

```yaml
# config/routes/phil_legal_notices.yaml
phil_legal_notices:
    resource: '@PhilLegalNoticesBundle/config/routes.php'
    prefix: { en: '', fr: '/fr' }
```

A prefix per locale makes the routes localized: `/legal` in English, `/fr/legal` in French, the locale of the request
set by the route. Mounted without a prefix, the pages follow the locale the application sets on the request.

## A first document

Fill in the identity in `config/packages/phil_legal_notices.yaml`, adapt the body in `legal/`, then publish the
version: give it a `published_at` and remove its `state: draft` — see [Configuration](configuration.md). A body names
the fields of the identity between `${` and `}`:

```markdown
# Legal notice

This site is published by **${publisher_name}**, ${publisher_address}.
Director of publication: ${publication_director}. Contact: ${contact_email}.

It is hosted by ${host_name}, ${host_address} (${host_contact}).
```

Then run `bin/console phil:legal-notices:check`: it reports a placeholder without a value or a body that cannot be
read. The container build refuses a published document whose identity lacks a field it requires.

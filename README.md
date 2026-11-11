# PHIL

**Proclaim Honestly, Interact Liberally: open PHP libraries and Symfony bundles for websites that publish, take part
and protect their visitors.**

PHIL is a set of small `phil/*` packages, each doing one thing: a library in plain PHP, usually paired with a Symfony
bundle that wires it into forms, validation, Twig, Doctrine and configuration. They are written from scratch, typed for
PHPStan at its maximum level, and tested down to the mutation of every line of their domain.

This repository is the **monorepo** where every package is developed, tested and documented together. Each package is
then split into its own **read-only** repository, from which Composer installs it through Packagist.

## Packages

### Enums

| Package | Description |
|---|---|
| [`phil/enums`](https://github.com/parler-haut-interagir-librement/enums) | Traits and attributes that make PHP enums easier to list, compare, describe and group into sets. |
| [`phil/enums-bundle`](https://github.com/parler-haut-interagir-librement/enums-bundle) | Your PHP enums in Symfony forms, translations, Twig, validation, Doctrine and API contracts. |

### Architecture

| Package | Description |
|---|---|
| [`phil/resource`](https://github.com/parler-haut-interagir-librement/resource) | Point at any record of your application by its kind and identifier, such as `page:42`. |
| [`phil/ddd-bundle`](https://github.com/parler-haut-interagir-librement/ddd-bundle) | The few types every bounded context shares: identifiers, domain events, errors, command and query buses. |

### Text and documents

| Package | Description |
|---|---|
| [`phil/slug`](https://github.com/parler-haut-interagir-librement/slug) | URL slugs made from titles and names, as a type that accepts nothing else. |
| [`phil/slug-bundle`](https://github.com/parler-haut-interagir-librement/slug-bundle) | Named sluggers declared in the configuration, autowired by name and usable in Twig. |
| [`phil/ujo`](https://github.com/parler-haut-interagir-librement/ujo) | UJO (Universal JSON Output) documents as values you can trust: read strictly, checked block by block, rendered as safe HTML. |
| [`phil/ujo-bundle`](https://github.com/parler-haut-interagir-librement/ujo-bundle) | UJO (Universal JSON Output) documents in a Symfony application: a field that checks them, a constraint, and safe HTML in Twig. |
| [`phil/typography`](https://github.com/parler-haut-interagir-librement/typography) | Real quotation marks, dashes, French spaces and soft hyphens in your HTML, in every language, without touching the markup. |
| [`phil/typography-bundle`](https://github.com/parler-haut-interagir-librement/typography-bundle) | Typographic quotes, dashes, French spaces and soft hyphens everywhere a Symfony application prints text. |
| [`phil/text-diff`](https://github.com/parler-haut-interagir-librement/text-diff) | What changed between two texts, word by word, paragraph by paragraph, or block by block — shown in HTML or listed. |
| [`phil/highlight`](https://github.com/parler-haut-interagir-librement/highlight) | Code in 24 languages coloured on the server, as plain HTML spans any stylesheet can theme. |
| [`phil/highlight-bundle`](https://github.com/parler-haut-interagir-librement/highlight-bundle) | Code coloured on the server in a Symfony application: in UJO documents, in templates, with a theme ready to link. |
| [`phil/css-sanitizer`](https://github.com/parler-haut-interagir-librement/css-sanitizer) | Let people style their pages without letting their CSS run code, load what it should not, or leave its frame. |
| [`phil/qr-code`](https://github.com/parler-haut-interagir-librement/qr-code) | QR codes drawn as SVG or PNG by PHP alone, with no service and no image library. |

### Forms and protection

| Package | Description |
|---|---|
| [`phil/spam-protection`](https://github.com/parler-haut-interagir-librement/spam-protection) | Spot spam in a form submission without a captcha, in plain PHP. |
| [`phil/spam-protection-bundle`](https://github.com/parler-haut-interagir-librement/spam-protection-bundle) | Keep spam out of your Symfony forms without a captcha, a third party or an identifier. |
| [`phil/bot-detection-bundle`](https://github.com/parler-haut-interagir-librement/bot-detection-bundle) | Tell a robot from a human in the browser, and let only that one word out. |

### Money, phone numbers and dates

| Package | Description |
|---|---|
| [`phil/money`](https://github.com/parler-haut-interagir-librement/money) | Amounts of money as exact integers in a known currency, with no float anywhere. |
| [`phil/money-bundle`](https://github.com/parler-haut-interagir-librement/money-bundle) | Money amounts in Symfony forms, validation, Twig templates and Doctrine entities, without floats. |
| [`phil/phone`](https://github.com/parler-haut-interagir-librement/phone) | Phone numbers read as people type them, checked against their country's plan, and formatted. |
| [`phil/phone-bundle`](https://github.com/parler-haut-interagir-librement/phone-bundle) | A phone number field, a validation constraint, a column type and Twig filters for Symfony. |
| [`phil/recurrence`](https://github.com/parler-haut-interagir-librement/recurrence) | Repeating dates from an RFC 5545 rule: read it, check it, write it, list its occurrences. |
| [`phil/recurrence-bundle`](https://github.com/parler-haut-interagir-librement/recurrence-bundle) | A form for repeating events, a column to store the rule, and the rule in words. |

### Integrations

| Package | Description |
|---|---|
| [`phil/standard-webhooks`](https://github.com/parler-haut-interagir-librement/standard-webhooks) | Sign and verify webhooks that any Standard Webhooks library can send or check. |
| [`phil/standard-webhooks-bundle`](https://github.com/parler-haut-interagir-librement/standard-webhooks-bundle) | Verified webhook deliveries as controller arguments, and signed webhooks sent from Symfony. |
| [`phil/circuit-breaker`](https://github.com/parler-haut-interagir-librement/circuit-breaker) | One circuit breaker per third party: calls stop at once when it keeps failing, and resume when it recovers. |
| [`phil/circuit-breaker-bundle`](https://github.com/parler-haut-interagir-librement/circuit-breaker-bundle) | Circuits declared in configuration, their states shared by the workers, and HTTP clients behind their circuits. |

Every package is at `0.x`: its API may still change between minor versions, as its CHANGELOG records. More packages
join the showcase by small daily groups.

## Installing a package

Require the package you need; a bundle brings its library along:

```sh
composer require phil/money-bundle
```

Each package's README and `doc/` say what it requires, how to configure it and what it leaves to the application.

## Layout

- `packages/<name>/`: one package per folder, installable on its own, with its `composer.json`, README in English and
  in French, CHANGELOG, licence, documentation in `doc/` (French mirror in `doc/fr/`) and tests.
- The root ties them together through a Composer path repository and holds the shared quality tooling, which comes
  from [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contributing

Issues and pull requests go to **this repository**, never to the split repositories, which are overwritten at each
split. Read the [contribution guide](CONTRIBUTING.md) and the [code of conduct](CODE_OF_CONDUCT.md). Report a
vulnerability privately, as the [security policy](SECURITY.md) explains, never in a public issue.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). Each package carries its own licence file.

# phil/profile-secure-input

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/profile-secure-input/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/profile-secure-input)

**One `composer require` for the PHIL packages that guard what users send a Symfony application: the addresses they
type, the addresses the application then fetches, how often they may ask, the spam in their forms and the files they
upload.**

phil/profile-secure-input is a Composer metapackage: it holds no code. Requiring it installs five PHIL packages that
are often used together where an application takes input from the public. Each member stays an independent package,
with its own configuration, documentation and releases: the profile couples no service to another, it only saves
looking for them one by one and picking constraints that work together.

It is for Symfony applications with public forms — sign-up, contact, comments, submissions — and links or files
given by visitors. An application that needs only one of these guards requires that member alone.

## What it installs

| Member | Constraint | Brings | Why it belongs here |
|---|---|---|---|
| `phil/url-bundle` | `^0.1.3` | `phil/url`, `phil/enums` | Every address a person types is read by PHP's WHATWG parser, normalised and typed before it is stored: a link form field, a validation constraint, a Doctrine column type. |
| `phil/safe-http` | `^0.2` | `phil/url` | When the application fetches an address a user gave — a preview, a feed, a webhook —, the client refuses private networks, checks each redirect and bounds time and size. |
| `phil/rate-limit-bundle` | `^0.1` | `phil/rate-limit` | Limits declared on controllers, routes and forms answer 429 with the standard RateLimit headers, and the container refuses a limiter that counts in a store each server keeps for itself. |
| `phil/spam-protection-bundle` | `^0.2` | `phil/spam-protection`, `phil/bot-detection-bundle`, `phil/rate-limit` | Spam is kept out of forms without a captcha, a third party or tracking: signed tokens, filling time, a small proof of work, content rules. |
| `phil/upload-scan-bundle` | `^0.1.1` | `phil/upload-scan` | Each uploaded file goes through an antivirus as the form is validated; an infected file is quarantined out of reach. |

The [members](doc/members.md) page gives each one's role, what it adds to the application and what it needs.

## What each member brings to the application

No member ships a Symfony Flex recipe: Flex registers each bundle in `config/bundles.php` and copies no file. Each
member then works with its defaults, and its own documentation says what to configure.

| Member | Bundle registered | Files it provides |
|---|---|---|
| `phil/url-bundle` | `Phil\UrlBundle\PhilUrlBundle` | Translations of its messages |
| `phil/rate-limit-bundle` | `Phil\RateLimitBundle\PhilRateLimitBundle` | The template of a refusal, translations |
| `phil/spam-protection-bundle` | `Phil\SpamProtectionBundle\PhilSpamProtectionBundle` | The Stimulus proof-of-work controller in `assets/package.json`, translations |
| `phil/bot-detection-bundle` | `Phil\BotDetectionBundle\PhilBotDetectionBundle` | The Stimulus detectors in `assets/package.json` |
| `phil/upload-scan-bundle` | `Phil\UploadScanBundle\PhilUploadScanBundle` | The routes of the quarantine page in `config/admin_routes.php`, its templates |
| `phil/safe-http` | none: a library | none |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | The stability a PHIL alpha needs, registering the bundles without Flex, what to configure next. |
| [Members](doc/members.md) | Each member: its role, what it adds, what it needs, its documentation. |
| [Upgrading](doc/upgrading.md) | How the constraints move, and how to leave out one member. |
| [Extension points](doc/extension-points.md) | Where an application changes the behaviour: in the members. |

## Quick start

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-secure-input
```

To leave out one member — here `phil/upload-scan-bundle` —, require the others directly instead of the profile:

```sh
composer remove phil/profile-secure-input
composer require phil/url-bundle:^0.1.3 phil/safe-http:^0.2 phil/rate-limit-bundle:^0.1 phil/spam-protection-bundle:^0.2
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later, with the extensions the members need: `ext-intl`, `ext-mbstring`,
`ext-sodium` and `ext-filter`. The members are alpha releases: the project accepts them with
`minimum-stability: alpha` and `prefer-stable: true`. Scanning uploads needs a ClamAV daemon.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

# Installation

## Stability

The members are alpha releases, published as `0.x.y-alpha`. Composer installs them in a project that accepts that
stability; `prefer-stable` keeps every other package at its stable release when there is one:

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-secure-input
```

The members require Symfony 8.2 or later: while 8.2 has no stable release, the same settings let Composer install its
beta or release candidate.

A stability flag on the requirement alone, `phil/profile-secure-input:@alpha`, is not enough: Composer applies flags
to the packages the project names, never to the members a metapackage requires.

## Registering the bundles

With Symfony Flex, each bundle is added to `config/bundles.php` as it is installed. Without Flex, add them:

```php
// config/bundles.php
return [
    // …
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
    Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
    Phil\BotDetectionBundle\PhilBotDetectionBundle::class => ['all' => true],
    Phil\SpamProtectionBundle\PhilSpamProtectionBundle::class => ['all' => true],
    Phil\UploadScanBundle\PhilUploadScanBundle::class => ['all' => true],
];
```

`phil/safe-http` is a library: its client is built where the application needs it, with no bundle.

## What the application brings

| Package or service | Why |
|---|---|
| `doctrine/dbal` | `phil/url-bundle` 0.1.2-alpha loads its Doctrine column type when the kernel boots, and needs DBAL for it. |
| CSRF protection and sessions | With Twig and `symfony/security-csrf` installed, `phil/upload-scan-bundle` registers the page of its quarantine, which asks for the CSRF token manager: `framework.csrf_protection` must then be on. |
| A ClamAV daemon | `phil/upload-scan-bundle` scans through clamd, on `tcp://127.0.0.1:3310` unless configured otherwise. |
| StimulusBundle with AssetMapper | The browser parts of `phil/spam-protection-bundle` and `phil/bot-detection-bundle` are Stimulus controllers. |

## What to configure next

Every member works with its defaults once registered. The usual next steps:

1. Declare the limits of the application in `phil_rate_limit`, and put `#[RateLimit]` on the controllers it protects.
2. Add the `spam_protection` option to the public forms.
3. Put `#[CleanFile]` on the upload fields, and import the routes of the quarantine page if administrators use it.
4. Use `TypedUrlType` and `#[UrlOfType]` for the links people give, and `SafeHttpClient` wherever the application
   fetches one.

The [members](members.md) page links the documentation of each one.

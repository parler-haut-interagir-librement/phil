# phil/url

**Web addresses as normalised values, with their kind detected: Facebook, YouTube, Mastodon, a website.**

`phil/url` reads an absolute `http` or `https` address into a `Url` as a browser reads it, on PHP's WHATWG URL parser,
normalised: lowercase host, default port dropped, international host names in punycode, the segments `.` and `..`
resolved, and a `display()` form as people read it. Kinds of address are enum
cases implementing `UrlType`, each carrying its own rule: a Facebook page is an address on `facebook.com`.
`KnownUrlType` holds 26 international kinds, from Facebook and YouTube to Mastodon on any server and a plain website.
`UrlTypes` detects which kind an address is among the enum sets a field accepts, and `CookielessVideo` turns a
YouTube or Vimeo video into its player that writes no cookies. **It does not** fetch addresses,
follow redirects, check that a page exists, or hold the kinds of a region.

It is for applications where people enter links to their profiles and sites: a directory, a member page, an event
listing. The kinds of a region, such as a business registry or a government portal, belong to the application as its
own enum, accepted next to `KnownUrlType`. Detection takes the first case that accepts, set by set, so specific cases
go first and catch-all ones last.

`phil/url` is a plain PHP library on `ext-intl`, `ext-mbstring` and `phil/enums`, whose `EnumSet` carries the
accepted kinds. In a Symfony application, reach for `phil/url-bundle`: a form field that detects the kind, the
constraint `UrlOfType`, a Doctrine column type and Twig filters. The design rests on value types and enums: a
`TypedUrl` is an address with its kind, and a kind is any case of any enum that says whether it accepts an address.

## Status

| Part | State |
|---|---|
| `Url`: parsing, normalisation, international hosts, display | Implemented and tested |
| `UrlType`, and `KnownUrlType`: 26 international kinds | Implemented and tested |
| `TypedUrl`, and `UrlTypes`: detecting the kind among enum sets | Implemented and tested |
| `CookielessVideo`: YouTube and Vimeo players without cookies | Implemented and tested |
| Exceptions under one `UrlException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Addresses](doc/addresses.md) | `Url`: what is accepted, how it is normalised and shown. |
| [Kinds](doc/kinds.md) | `UrlType`, the international kinds, an application's own, detection among enum sets, video players without cookies. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/url
```

```php
use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\Url\UrlTypes;

$social = EnumSet::of(KnownUrlType::class, KnownUrlType::Facebook, KnownUrlType::Instagram);

$typed = UrlTypes::type('https://www.instagram.com/shop', $social);
$typed->type;              // KnownUrlType::Instagram
$typed->url->display();    // "instagram.com/shop"
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`; `phil/enums` 0.3.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

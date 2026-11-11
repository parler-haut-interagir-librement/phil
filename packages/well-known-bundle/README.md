# phil/well-known-bundle

**The `/.well-known/` addresses of a Symfony site, each turned on in its configuration.**

phil/well-known-bundle serves the documents other software looks for at fixed addresses of a domain (RFC 8615): how
to report a vulnerability (`security.txt`), where to change a password, what the site says of an account (WebFinger),
which software it runs (NodeInfo), the DID document of a `did:web` identifier and the credentials linking the domain to
its DIDs, the DID an AT Protocol handle resolves to, and the app links of iOS and Android apps. Fixed documents of the
configuration and documents of other bundles join them, each at its own exact route.

Use it in a Symfony site that wants these addresses right without writing a controller for each: the media types,
the CORS header of the documents read by other sites, cache headers and an `ETag`, `X-Robots-Tag: noindex`, no session
and no cookie. A document that is not turned on has no route, and is a `404`. A value a document could not serve, such
as a security.txt that has expired or a DID that is not one, stops the container from being built.

It plugs into the Symfony router, by a route loader, and the container: WebFinger resolvers are autoconfigured, so an
ActivityPub bundle adds the links of its accounts by implementing `WebFingerResolver`, and any bundle adds a document by
implementing `WellKnownDocument` with `#[AsWellKnownDocument]`.

## Status

| Part | State |
|---|---|
| `security.txt` (RFC 9116), checked when the container is built, signed by a port | Implemented and tested |
| `change-password` | Implemented and tested |
| WebFinger (RFC 7033): `acct:` and `https:` resources, `rel`, resolvers | Implemented, tested and fuzzed |
| NodeInfo 2.1 and its discovery document, with a provider port | Implemented and tested |
| `did.json`, `did-configuration.json`, `atproto-did` | Implemented and tested |
| `apple-app-site-association`, `assetlinks.json` | Implemented and tested |
| Fixed documents, documents of other bundles | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the routes. |
| [Configuration](doc/configuration.md) | Every option, with its default. |
| [Documents](doc/documents.md) | What each document serves, and with which headers. |
| [WebFinger](doc/webfinger.md) | Queries, resources, resolvers and what WebFinger tells. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested, fuzzed and measured. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
# config/routes/phil_well_known.yaml
phil_well_known:
    resource: '@PhilWellKnownBundle/config/routes.php'
```

```yaml
# config/packages/phil_well_known.yaml
phil_well_known:
    security_txt:
        contact: ['mailto:security@example.com']
        expires: '2027-06-30T00:00:00Z'
    change_password:
        route: account_password
    webfinger: true
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later: HttpKernel, Routing, Config and DependencyInjection.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

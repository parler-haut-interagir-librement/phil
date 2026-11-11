# phil/ujo-bundle

**UJO (Universal JSON Output) documents in a Symfony application: a field that checks them, a constraint, and safe HTML in Twig.**

phil/ujo-bundle integrates `phil/ujo` into Symfony. It declares the library's parts as services built from a short
configuration — whether raw HTML blocks are accepted, how large a document may be, how deep blocks may nest —, adds
the `ujo_html` and `ujo_text` filters and functions to Twig, a `UjoType` form field that receives the JSON a block
editor writes and keeps it in canonical form, and a `ValidUjo` constraint that checks a document and, if asked, the
types of its blocks. It does not ship the block editor itself, nor store documents.

It is for any Symfony application whose writers compose in a block editor that writes UJO: the field and the
constraint stop an invalid or unwanted document at the form, and the filters render stored documents purified, under
today's rules.

It is a Symfony bundle on `phil/ujo`, `symfony/form` and `symfony/validator`. Twig is optional: the filters and
functions are registered when it is installed. Every service is the library's own class, declared under its class
name, so an application replaces any of them by redefining it.

## Status

| Part | State |
|---|---|
| Configuration: `allow_raw`, `max_bytes`, `max_depth`, `max_blocks`, `max_html_length`, `max_raw_length` | Implemented and tested |
| Services: `BlockTypeRegistry`, `Normalizer`, `HtmlRenderer`, `PlainText`, `JsonDecoder`, `Ujo` | Implemented and tested |
| `ujo_html` and `ujo_text`, filters and functions | Implemented and tested |
| `UjoType` and `UjoTransformer` | Implemented and tested |
| `ValidUjo`, with accepted types and a validation level, shape or publication; messages in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Every option, the services, and replacing one. |
| [Twig](doc/twig.md) | `ujo_html` and `ujo_text`. |
| [Forms and validation](doc/forms-and-validation.md) | `UjoType`, its options, and the `ValidUjo` constraint. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test a form that holds a document. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require phil/ujo-bundle
```

```php
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;

$builder->add('body', UjoType::class);            // canonical JSON in, canonical JSON out

#[ValidUjo(types: ['paragraph', 'header', 'list', 'quote', 'image'])]
public ?string $body = null;
```

```twig
{{ article.body|ujo_html }}
<meta name="description" content="{{ article.body|ujo_text|u.truncate(160) }}">
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/ujo`. Twig 3.21, for the filters and functions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).

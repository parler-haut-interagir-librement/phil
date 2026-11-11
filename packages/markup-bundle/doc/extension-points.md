# Extension points

What an application configures, replaces or decorates. The compilers, purifiers and readers are `phil/markup`'s.

## Configuration

| Option | What it extends |
|---|---|
| `formats`, `default_format` | The formats texts may be written in, in the order the form offers them, and the one taken when none is named |
| `added_formats` | The formats add-ons bring: their label, and the service of their `ReaderProvider`; an add-on bundle prepends its own |
| `levels`, `default_level` | The highest level each format is purified at, whatever a template asks, and the level taken when none is named |
| `smart_punctuation` | Whether, and in which locale, the HTML goes through a typographer before it is purified |
| `media_hosts` | The hosts images may come from, given to `SanitizerPurifier` whichever bundle registers it |

See [Configuration](configuration.md).

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\Markup\MarkupCompiler` | An alias: pointed at the application's own compiler — `#[AsAlias(MarkupCompiler::class)]` — and `Markup`, the filters and the bundles that compile follow; the smart punctuation decorates whatever it points at |
| `Phil\Markup\HtmlPurifier` | An alias: pointed at the application's own purifier the same way; a purifier the application builds itself keeps its own image hosts |
| `Phil\Ujo\Html\HtmlRenderer` | The renderer `DefaultCompiler` renders UJO with: the application's block renderers, through the factory `phil/ujo-bundle` documents, render its own blocks in every format |
| `Phil\Markup\Markup` | Injected wherever texts are compiled and purified; public |
| `Phil\MarkupBundle\Infrastructure\Format\MarkupFormats` | Injected to read the enabled formats and levels; public |
| `Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter` | Injected to convert a text into UJO; reads with `DefaultCompiler` whatever `MarkupCompiler` points at; public |

An application's own compiler changes the HTML, not the readers: what the converter reads stays the library's, and
the readers of the formats declared in `added_formats`.

## Dialects and formats of add-ons

A dialect of Markdown — its inline and block rules, the syntax it reads differently — is a `MarkdownDialect` of
`phil/markup`, given to a `MarkdownReader`; a whole format is a `ReaderProvider`, which gives that reader, or one of its
own. An add-on bundle registers its provider as a service, declares it in `added_formats` from `prependExtension()`,
and ships the translations of its label in the `phil_markup` domain:

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_markup', ['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => ShoutReaders::class]]]);
}

public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->register(ShoutReaders::class, ShoutReaders::class);
}
```

The application then enables the format, or leaves `formats` empty to take every one.

## Forms and Twig

| Point | How |
|---|---|
| The formats a field offers | `MarkupFormatType`, among the enabled formats — see [Forms](forms.md) |
| Compiling in templates | `phil_markup`, `phil_purify` and `phil_ujo`, with a format and a level under the format's ceiling — see [Twig](twig.md) |

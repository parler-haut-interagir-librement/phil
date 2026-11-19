# Installation

```sh
composer require phil/braille-export
```

The package needs PHP 8.5 or later with `mbstring`, and `phil/ujo` 0.1, which it reads UJO documents with. It starts
no program and needs no service.

## Choosing a translator

| The edition | The translator |
|---|---|
| Uncontracted French or English | `UncontractedTranslator`, in the package |
| Contracted braille, or another language | A translator with full tables: `LiblouisTranslator` of `phil/braille-export-liblouis` |
| Contracted braille when the machine has the tables, uncontracted otherwise | `new ChainTranslator($liblouis, new UncontractedTranslator())` |

See [Translation](translation.md).

## Wiring the services

The package has no bundle: its classes are values and stateless services, each built with `new`. In a Symfony
application, `config/services.yaml` can name them:

```yaml
services:
    Phil\BrailleExport\Contract\Translator: { class: Phil\BrailleExport\Translation\UncontractedTranslator }
    Phil\BrailleExport\Layout\PageFormat: ~
    Phil\BrailleExport\BrailleExporter: { autowire: true }
    Phil\BrailleExport\Output\BrfWriter: ~
    Phil\BrailleExport\Output\PefWriter: ~
    Phil\BrailleExport\Output\UnicodeWriter: ~
```

None of them keeps anything from one document to the next, so they serve a worker that lives across requests as they
serve one request.

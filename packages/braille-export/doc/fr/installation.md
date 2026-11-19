<!-- phil-dev source-sha256=cde2a3dd59efc93099d0a9f82eb3529df2d73641c304a6dfdd81015a16b75a5d -->
# Installation

```sh
composer require phil/braille-export
```

Le paquet exige PHP 8.5 ou une version plus récente avec `mbstring`, et `phil/ujo` 0.1, avec lequel il lit les
documents UJO. Il ne démarre aucun programme et n'a besoin d'aucun service.

## Choisir un traducteur

| L'édition | Le traducteur |
|---|---|
| Français ou anglais intégral | `UncontractedTranslator`, dans le paquet |
| Braille abrégé, ou une autre langue | Un traducteur à tables complètes : `LiblouisTranslator` de `phil/braille-export-liblouis` |
| Braille abrégé quand la machine a les tables, intégral sinon | `new ChainTranslator($liblouis, new UncontractedTranslator())` |

Voir [Traduction](translation.md).

## Brancher les services

Le paquet n'a pas de bundle : ses classes sont des valeurs et des services sans état, construits chacun avec `new`.
Dans une application Symfony, `config/services.yaml` peut les nommer :

```yaml
services:
    Phil\BrailleExport\Contract\Translator: { class: Phil\BrailleExport\Translation\UncontractedTranslator }
    Phil\BrailleExport\Layout\PageFormat: ~
    Phil\BrailleExport\BrailleExporter: { autowire: true }
    Phil\BrailleExport\Output\BrfWriter: ~
    Phil\BrailleExport\Output\PefWriter: ~
    Phil\BrailleExport\Output\UnicodeWriter: ~
```

Aucun d'eux ne garde quoi que ce soit d'un document au suivant : ils servent un *worker* qui vit au fil des requêtes
comme ils servent une seule requête.

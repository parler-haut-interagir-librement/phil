<!-- phil-dev source-sha256=6150cf71860430da75a6fe5f89024b71aa25ff40a9749b28d7cfa51f4f5ec4f9 -->
# Installation

```sh
composer require phil/braille-export-liblouis
```

Le paquet exige PHP 8.5 ou une version plus récente avec `mbstring`, `phil/braille-export` 0.1 et `symfony/process` 8.2.

## Installer liblouis

liblouis est une bibliothèque et un ensemble de programmes sous la LGPL, avec ses tables de traduction ; le paquet
exécute son programme `lou_translate` et n'en fournit rien. Installez-le sur chaque machine qui traduit.

| Système | Commande |
|---|---|
| Debian, Ubuntu | `apt-get install liblouis-bin liblouis-data` |
| macOS, avec Homebrew | `brew install liblouis` |

Dans une image Docker de l'application, la même ligne va dans son `Dockerfile` :

```dockerfile
FROM php:8.5-cli
RUN apt-get update \
    && apt-get install --no-install-recommends -y liblouis-bin liblouis-data \
    && rm -rf /var/lib/apt/lists/*
```

`lou_translate --version` nomme alors la version, et `LiblouisTranslator::version()` la lit. Le paquet est écrit pour
liblouis 3.

Les tables sont installées dans le répertoire des tables de liblouis, comme `/usr/share/liblouis/tables`. Des tables
gardées ailleurs se donnent par `$tablePath` ; voir [Configuration](configuration.md).

## Brancher le service

Le paquet n'a pas de bundle. Dans une application Symfony, `config/services.yaml` en fait le traducteur, avec le
traducteur intégral derrière lui :

```yaml
services:
    Phil\BrailleExportLiblouis\LiblouisTranslator: ~
    Phil\BrailleExport\Translation\UncontractedTranslator: ~
    Phil\BrailleExport\Contract\Translator:
        class: Phil\BrailleExport\Translation\ChainTranslator
        arguments:
            - '@Phil\BrailleExportLiblouis\LiblouisTranslator'
            - '@Phil\BrailleExport\Translation\UncontractedTranslator'
```

`LiblouisTranslator::isAvailable()` dit si le programme est trouvé, pour une vérification d'état ou de déploiement.
`supports()` ne lit que les tables : sur une machine sans liblouis, la chaîne donne quand même un lot abrégé à liblouis,
et `ExecutableNotFound` le dit. Une application qui préfère se rabattre sur le braille intégral ne met
`LiblouisTranslator` dans sa chaîne que lorsque `isAvailable()` répond oui.

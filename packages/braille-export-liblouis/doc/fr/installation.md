<!-- phil-dev source-sha256=e25864509bbbbb013f176eff80dc6c35741d75884b541518ab73dc575b59f753 -->
# Installation

```sh
composer require phil/braille-export-liblouis
```

Le paquet exige PHP 8.5 ou une version plus récente avec `mbstring`, `phil/braille-export` 0.1 et `symfony/process` 8.2.

## Installer liblouis

liblouis est une bibliothèque sous la LGPL 2.1 ou plus récente ; ses tables de traduction et ses programmes en ligne de
commande, dont `lou_translate`, sont sous la GPL 3 ou plus récente. Le paquet exécute `lou_translate` comme un programme
distinct, par Symfony Process : il n'en fournit aucune partie, n'est lié à aucune et n'en charge aucune dans PHP, de
sorte que sa propre licence MIT reste entière et que les conditions de liblouis ne lient que la machine qui l'installe.
Installez-le sur chaque machine qui traduit.

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

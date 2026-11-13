<!-- phil-dev source-sha256=c56d6bb63f222abf9259251c3bcbc5c0a14001f34ccb940ea254ef5fca4c955a -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP, avec `dom`, `libxml` et `sodium` | 8.5 ou plus récent | Le plancher que déclare le paquet ; `dom` et `libxml` lisent le document pour `LocalAssets`, `sodium` encode ses fichiers |
| `phil/paged-media-bundle` | ^0.1 | Le port `PrintEngine` et `PrintableHtml` |
| `symfony/process` | ^8.2 | Exécute la commande |
| La commande `weasyprint` | 70 ou plus récente | Met le document en page |

```sh
composer require phil/paged-media-weasyprint
```

Composer n'installe pas WeasyPrint. Il vient avec la machine : un paquet du système, une installation par `pip` dans
un environnement virtuel, ou l'image de l'application. WeasyPrint a besoin de Python et de Pango ; sa propre
documentation les énumère pour chaque système.

## La version de WeasyPrint

Le paquet est écrit et testé pour WeasyPrint 70, la valeur de `WeasyPrintEngine::MINIMUM_VERSION`.

```php
$engine->version();          // "70.0", from `weasyprint --version`
$engine->assertSupported();  // throws WeasyPrintFailed on an older command
```

`assertSupported()` lance la commande : elle a donc sa place dans une étape de déploiement ou une sonde de santé, pas
avant chaque impression. Une commande assez ancienne pour ne pas connaître `--allowed-protocols` s'arrête avec le code
de sortie 2 à la première impression : les arguments qui la confinent ne sont jamais abandonnés en silence.

## Brancher le service

Le paquet n'a pas de bundle. L'application enregistre le moteur sous le port, puisque `paged-media-bundle` cherche le
port par son nom et laisse `printHtml()` lancer `NotConfigured` quand il n'y en a pas. Une ligne suffit, avec les
valeurs par défaut — la commande `weasyprint` du `PATH`, 120 secondes, aucun fichier du disque :

```yaml
# config/services.yaml
services:
    Phil\PagedMediaBundle\Application\Contract\PrintEngine: { class: Phil\PagedMediaWeasyprint\WeasyPrintEngine }
```

Quand le câblage automatique est actif, un service `LocalAssets` de l'application est aussi donné au moteur. Chaque
argument peut plutôt être fixé :

```yaml
# config/services.yaml
services:
    Phil\PagedMediaWeasyprint\LocalAssets:
        arguments:
            $directory: '%kernel.project_dir%/assets/print'

    Phil\PagedMediaBundle\Application\Contract\PrintEngine:
        class: Phil\PagedMediaWeasyprint\WeasyPrintEngine
        arguments:
            $binary: '%env(WEASYPRINT_BINARY)%'    # the default is `weasyprint`, found on the PATH
            $timeout: 300.0                        # seconds; the default is 120
            $assets: '@Phil\PagedMediaWeasyprint\LocalAssets'
            $pdf: !service
                class: Phil\PagedMediaWeasyprint\PdfOptions
                arguments: [!php/enum Phil\PagedMediaWeasyprint\PdfVariant::PdfA2u]
```
 `$assets` est omis quand les documents ne prennent aucun fichier sur le disque, et `$pdf` quand les valeurs par défaut
de WeasyPrint conviennent ; voyez [Sortie PDF](pdf-output.md). Le choix reste à l'application : installer un paquet ne
devrait pas décider quel moteur imprime, et un site peut en avoir un autre, ou un délai
différent selon l'environnement.

Les classes ne gardent aucun état entre deux appels et peuvent rester en mémoire dans un *worker*.

Sans le conteneur de Symfony, le moteur est une classe comme une autre :

```php
use Phil\PagedMediaWeasyprint\LocalAssets;
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;

$engine = new WeasyPrintEngine('/usr/local/bin/weasyprint', 300.0, new LocalAssets('/srv/print-assets'), new PdfOptions(PdfVariant::PdfA2u));
```

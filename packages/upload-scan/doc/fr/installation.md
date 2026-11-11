<!-- phil-dev source-sha256=2eef7d74250cd3b2a11c150a046708ef72fdb22a6a2688085be7d99328885dc8 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses. |
| clamd | toute version prise en charge de ClamAV | Pour `ClamdScanner` : le démon qui analyse, sur un socket Unix ou un port TCP. |

La bibliothèque n'a besoin d'aucune extension PHP au-delà de celles d'une construction par défaut, ni d'aucun client
tiers de ClamAV.

## Installer

```sh
composer require phil/upload-scan
```

## Construire un analyseur

```php
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Clamd\SocketConnector;

$scanner = ClamdScanner::at('unix:///run/clamav/clamd.ctl');

// Or every setting given:
$scanner = new ClamdScanner(
    new SocketConnector('tcp://clamav:3310', connectTimeoutSeconds: 2.0),
    timeoutSeconds: 30.0,
    maxBytes: 26_214_400,
    chunkBytes: 65_536,
);
```

Un analyseur ne garde rien entre deux fichiers : il ouvre une connexion par fichier et la ferme une fois la réponse
reçue. Une seule instance sert donc chaque requête d'un *worker* de longue durée.

Dans Symfony, `phil/upload-scan-bundle` construit l'analyseur à partir de la configuration et ajoute une contrainte de
validation, une quarantaine et des événements.

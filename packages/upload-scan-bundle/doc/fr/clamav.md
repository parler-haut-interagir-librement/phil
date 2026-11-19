<!-- phil-dev source-sha256=72006adfbe1b44f94f02ae590491ad458b8e0a86d2cf6b7374df12cc8e6f0cea -->
# ClamAV

Le bundle a besoin d'un clamd qu'il peut joindre. En développement comme en production, le plus simple est un service
`clamav` à côté de l'application, à partir de l'image officielle.

## Docker Compose

```yaml
# compose.yaml
services:
    clamav:
        image: clamav/clamav:stable
        restart: unless-stopped
        volumes:
            - clamav-signatures:/var/lib/clamav   # keeps the signatures between restarts
        healthcheck:
            test: ["CMD", "clamdcheck.sh"]
            interval: 60s
            start_period: 120s

    php:
        # …
        depends_on:
            clamav:
                condition: service_healthy

volumes:
    clamav-signatures:
```

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    clamd:
        address: tcp://clamav:3310
```

- Le port 3310 reste sur le réseau de Compose : clamd n'a aucune authentification, ne le publiez jamais.
- L'image met à jour ses signatures elle-même avec `freshclam`. Le premier démarrage les télécharge et prend une ou
  deux minutes ; le contrôle de santé attend que clamd réponde.
- clamd garde ses signatures en mémoire : prévoyez environ 1,5 Gio de mémoire pour le service, davantage pendant qu'il
  les recharge.
- clamd refuse un flux plus gros que `StreamMaxLength`, 25 Mio par défaut. Pour analyser de plus gros fichiers,
  augmentez à la fois cette valeur dans le `clamd.conf` du service et `phil_upload_scan.max_bytes`.

## Sur le même hôte

Avec le paquet `clamav-daemon` de la distribution, clamd écoute sur son socket Unix :

```yaml
phil_upload_scan:
    clamd:
        address: unix:///run/clamav/clamd.ctl
```

L'utilisateur sous lequel tourne PHP doit avoir le droit d'ouvrir le socket : ajoutez-le au groupe `clamav`, ou
réglez `LocalSocketGroup` dans `clamd.conf`, avec `LocalSocketMode 660`, et redémarrez PHP-FPM ou le *worker* pour qu'il
prenne le groupe. Ne rendez jamais le socket accessible en écriture à tous. La configuration complète — `clamd.conf`, le
répertoire du socket, l'unité socket de systemd et un socket partagé entre des conteneurs — se trouve dans la
documentation de `phil/upload-scan`, sous « clamd on a local Unix socket ».

## Vérifier qu'il répond

Avec `phil/monitoring-bundle`, le contrôle de santé `upload_scan` affiche la version de clamd et celle de ses
signatures. Sans lui, `printf 'zVERSION\0' | nc clamav 3310` lancé depuis le conteneur PHP donne la même réponse.

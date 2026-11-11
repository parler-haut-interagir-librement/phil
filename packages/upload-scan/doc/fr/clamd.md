<!-- phil-dev source-sha256=522a0e7bb02d6aed1b9a29bb2f4dfb25c82df4838015f485d9adfe741de2d55f -->
# clamd

`ClamdScanner` analyse par clamd, le démon de ClamAV, selon le protocole que documente clamd. La bibliothèque le parle
elle-même : aucun paquet client, aucun processus `clamdscan`.

## Le protocole

Chaque commande est une connexion, sous la forme terminée par un octet nul (préfixe `z`) :

| Commande | Envoyé | Répondu |
|---|---|---|
| `INSTREAM` | `zINSTREAM\0`, puis chaque morceau précédé de sa longueur sur quatre octets dans l'ordre réseau, puis quatre octets nuls | `stream: OK\0`, `stream: <signature> FOUND\0`, ou `<message> ERROR\0` |
| `PING` | `zPING\0` | `PONG\0` |
| `VERSION` | `zVERSION\0` | `ClamAV <version>/<signatures>/<date>\0` |

Le fichier voyage dans la connexion : clamd n'a besoin d'aucun accès au disque de l'application et peut s'exécuter dans
son propre conteneur ou sur son propre hôte.

`ClamdResponse` lit les réponses. Quels que soient les octets, il répond par un résultat. Une réponse qui se termine
par `FOUND` est une infection, même si sa signature est illisible (la signature est alors réduite à son ASCII
imprimable, ou devient `Unnamed`). Une réponse qui se termine par `ERROR` est un résultat d'erreur avec le message de
clamd. Tout le reste est un résultat d'erreur qui nomme ce que clamd a répondu. Il lit au plus 4 096 octets d'une
réponse, jusqu'à son premier octet nul.

## Adresses

| Adresse | Où |
|---|---|
| `unix:///run/clamav/clamd.ctl` | Le socket Unix de clamd sur le même hôte (`LocalSocket` dans `clamd.conf`). |
| `tcp://clamav:3310` | Le port TCP de clamd (`TCPSocket`), comme un service `clamav` du même projet Compose. |

`SocketConnector` refuse toute autre forme à sa construction. N'exposez le port TCP que sur un réseau privé : clamd n'a
aucune authentification.

## La limite de taille

`maxBytes`, 25 Mio par défaut, est le plus gros fichier envoyé. L'analyseur compte ce qu'il lit ; au-delà de la
limite, il cesse d'envoyer, ferme la connexion et répond par un résultat d'erreur, que la politique rejette ou signale.
clamd a sa propre limite, `StreamMaxLength` dans `clamd.conf`, aussi de 25 Mio par défaut : gardez `maxBytes` égal ou
inférieur à cette limite, sinon clamd répond lui-même `INSTREAM size limit exceeded. ERROR`, qui se lit aussi comme un
résultat d'erreur. Relevez les deux ensemble pour analyser de plus gros fichiers.

## Les délais d'attente

| Réglage | Valeur par défaut | Borne |
|---|---|---|
| `SocketConnector::$connectTimeoutSeconds` | 5 s | Le temps que peut prendre la connexion. |
| `timeoutSeconds` de `ClamdScanner` | 30 s | Le temps que peut attendre chaque lecture ou écriture sur la connexion. |

L'analyse d'une grosse archive peut prendre plusieurs secondes à clamd une fois le dernier morceau envoyé : le délai
d'attente borne l'attente de sa réponse. Quand il est dépassé, le résultat est
`clamd did not answer within N seconds.`.

## Les morceaux

`chunkBytes`, 64 Kio par défaut, est la taille de chaque morceau lu dans le fichier et envoyé. Chacun est écrit en
entier avant que le suivant soit lu : la mémoire que retient une analyse reste donc proche d'un morceau, quelle que soit
la taille du fichier.

## La sonde

`ping()` répond si clamd répond `PONG` ; `version()` répond ce qu'annonce clamd (la version du moteur, la version de
ses signatures et leur date), ou `null` quand il ne répond pas. Une vérification de santé appelle `version()` : des
signatures périmées s'y voient avant de laisser passer une menace.

<!-- phil-dev source-sha256=735c194e080907d44b9a9658511594fa14fde98f08996e2f052656ba928c283b -->
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
clamd. Tout le reste est un résultat d'erreur qui nomme ce que clamd a répondu.

`ClamdScanner` ne remet à `ClamdResponse` qu'une réponse entière : jusqu'à son octet nul, au plus 4 096 octets. Une
réponse tronquée est un résultat d'erreur, jamais lue pour ce qu'elle contient, pour que `stream: OK` sans son octet nul
ne soit pas un fichier sain :

| Ce qu'a fait clamd | Résultat |
|---|---|
| A fermé la connexion sans un octet | `clamd closed the connection without an answer.` |
| A fermé la connexion avant l'octet nul | `clamd closed the connection before the end of its answer.` |
| A envoyé 4 096 octets sans octet nul | `clamd answered more than 4096 bytes without ending its answer.` |
| N'a rien envoyé de plus dans le délai d'attente | `clamd did not answer within N seconds.` |

`ping()` répond `false` et `version()` `null` pour chacun d'eux.

## Adresses

| Adresse | Où |
|---|---|
| `unix:///run/clamav/clamd.ctl` | Le socket Unix de clamd sur le même hôte (`LocalSocket` dans `clamd.conf`). |
| `tcp://clamav:3310` | Le port TCP de clamd (`TCPSocket`), comme un service `clamav` du même projet Compose. |

`SocketConnector` refuse toute autre forme à sa construction. N'exposez le port TCP que sur un réseau privé : clamd n'a
aucune authentification.

## clamd sur un socket Unix local

Quand clamd s'exécute sur le même hôte que l'application, préférez son socket Unix à un port TCP : aucun port n'est
ouvert, et le système de fichiers décide qui peut analyser. Dans `clamd.conf` :

```
LocalSocket /run/clamav/clamd.ctl
LocalSocketGroup clamav
LocalSocketMode 660
FixStaleSocket yes
# No TCPSocket line: clamd listens on the Unix socket only.
StreamMaxLength 25M
```

- `LocalSocket` est le chemin donné à l'analyseur : `ClamdScanner::at('unix:///run/clamav/clamd.ctl')`. Les
  distributions utilisent `/run/clamav/clamd.ctl` ou `/var/run/clamav/clamd.sock` ; lisez `LocalSocket` dans le
  `clamd.conf` installé.
- `LocalSocketGroup` et `LocalSocketMode 660` ne laissent se connecter que l'utilisateur sous lequel tourne clamd et les
  membres de ce groupe. Ajoutez l'utilisateur sous lequel tourne PHP (comme `www-data`, ou l'utilisateur du pool de
  PHP-FPM) à ce groupe, puis redémarrez PHP-FPM ou le *worker* pour qu'il prenne le groupe : `usermod -aG clamav www-data`.
  Ne rendez jamais le socket accessible en écriture à tous (`666`) : n'importe quel utilisateur local pourrait alors
  faire lire des fichiers à clamd ou l'épuiser.
- Le répertoire du socket doit aussi être traversable par cet utilisateur (`/run/clamav` est en `755` ou appartient au
  groupe).
- Sous systemd, une unité `clamav-daemon.socket` peut créer le socket à la place de clamd : réglez ses `SocketGroup=` et
  `SocketMode=` de la même façon.
- Dans des conteneurs, partagez le répertoire du socket comme volume entre le conteneur de clamd et celui de
  l'application, et donnez aux deux le même identifiant de groupe, puisque les noms ne sont pas partagés entre les
  images.

Une analyse par un socket que l'application ne peut pas ouvrir répond par un résultat d'erreur qui nomme l'adresse, comme
`clamd cannot be reached at unix:///run/clamav/clamd.ctl (Permission denied).`, que la politique rejette ou signale.
`ping()` répond `false` : une vérification de santé montre la permission manquante avant le premier téléversement.

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

Le flux du fichier a son propre délai d'attente, comme celui d'un socket ou d'un flux distant. Quand il est dépassé, ou
quand le flux ne donne plus rien sans avoir pris fin, l'analyseur s'arrête avant le dernier morceau, pour que clamd ne
réponde jamais pour le seul début d'un fichier, et le résultat est une erreur : `The file stopped coming: its stream timed out before its end.`
ou `The file stopped before its end: its stream gave nothing more and did not end.`

## Les morceaux

`chunkBytes`, 64 Kio par défaut, est la taille de chaque morceau lu dans le fichier et envoyé. Chacun est écrit en
entier avant que le suivant soit lu : la mémoire que retient une analyse reste donc proche d'un morceau, quelle que soit
la taille du fichier.

## La sonde

`ping()` répond si clamd répond `PONG` ; `version()` répond ce qu'annonce clamd (la version du moteur, la version de
ses signatures et leur date), ou `null` quand il ne répond pas. Une vérification de santé appelle `version()` : des
signatures périmées s'y voient avant de laisser passer une menace.

<!-- phil-dev source-sha256=3ac10cbcb741f92628cb67b6a025880ab1bf7fa4320d0febf995765d0cbe2954 -->
# Adresses de service

Une application parle souvent à des services qu'elle exécute à côté d'elle : un serveur de reconnaissance vocale, un
moteur de rendu PDF, un moteur de recherche. Dans un réseau de conteneurs, leurs adresses nomment un hôte sans domaine
ou une adresse IP — `http://whisper:8080`, `http://gotenberg:3000`, `http://10.0.0.5:9200`. `Url::fromString()` refuse
ces hôtes, exprès : une adresse que donne un membre, un éditeur ou un autre site ne doit jamais nommer une machine du
réseau propre à l'application.

`ServiceUrl` les lit, pour les hôtes que l'application déclare et aucun autre.

## Déclarer les hôtes

Les hôtes viennent de la configuration de l'application, jamais d'un formulaire ni d'une requête :

```php
use Phil\Url\ServiceHosts;
use Phil\Url\ServiceUrl;

$hosts = new ServiceHosts('whisper', 'gotenberg', '10.0.0.5', '::1');

$whisper = ServiceUrl::fromString('http://whisper:8080', $hosts);
$whisper->toString();  // "http://whisper:8080/"
$whisper->origin();    // "http://whisper:8080"

ServiceUrl::fromString('http://redis:6379/', $hosts);     // InvalidUrl: "redis" is not a declared service host.
ServiceUrl::fromString('https://example.com/', $hosts);   // InvalidUrl: a public host is refused too
Url::fromString('http://whisper:8080');                   // InvalidUrl: "whisper" is not a host name.
```

| `ServiceHosts` accepte | Il refuse |
|---|---|
| Un nom sans domaine, `whisper` | Un port, `whisper:8080` — le port appartient à l'adresse |
| Un nom d'une zone privée, `pdf.internal` | Un chemin, une chaîne de requête, un fragment, un nom d'utilisateur, un échappement, une espace ou un caractère de contrôle |
| Une adresse IPv4, `10.0.0.5`, écrite courte ou en entier | Une zone IPv6, `fe80::1%eth0` |
| Une adresse IPv6, avec ou sans crochets, `::1` ou `[::1]` | Plus de 256 hôtes |

Chaque hôte est gardé comme une adresse le nomme : en minuscules, sous sa forme ASCII, un point final retiré, une
adresse IPv4 en entier (`127.1` devient `127.0.0.1`), une adresse IPv6 entre crochets et compressée (`[2001:DB8::0:1]`
devient `[2001:db8::1]`). `all()` les énumère ; `declares($host)` compare un hôte donné sous cette forme.

## Ce qu'une adresse de service garde de `Url`

`http` ou `https` seulement, ni nom d'utilisateur ni mot de passe, pas de port 0, 2048 caractères au plus, et le
chemin, la chaîne de requête et le fragment normalisés par le WHATWG URL Standard comme
[`Url` les normalise](addresses.md#normalisation). Un `ServiceUrl` a les parties `scheme`, `host`, `port`, `path`,
`query` et `fragment`, ainsi que `toString()`, `origin()` et `equals()`. Il n'a ni `display()`, ni `isOn()`, ni genre :
il n'est jamais montré aux gens ni détecté parmi des genres.

Un `ServiceUrl` n'est pas un `Url`, et aucun des deux ne se convertit en l'autre : un client de sites publics, dont
celui de `phil/safe-http`, ne reçoit jamais une adresse de service par erreur, et un client de service ne lit jamais
une adresse qu'un visiteur a écrite.

## Résoudre un point de terminaison

`resolve()` résout une référence contre l'adresse comme un navigateur résout un lien, et refuse celle qui quitte le
schéma, l'hôte ou le port :

```php
$api = ServiceUrl::fromString('http://whisper:8080/v1/', $hosts);

$api->resolve('inference');      // http://whisper:8080/v1/inference
$api->resolve('/health?full=1'); // http://whisper:8080/health?full=1
$api->resolve('//gotenberg/');   // InvalidUrl: leaves the service at http://whisper:8080
$api->resolve('https://whisper:8080/'); // InvalidUrl: another scheme
```

Une référence de plus de 2048 caractères est refusée avant d'être résolue.

## Les clients qui lisent leur adresse ainsi

- Un client d'un serveur whisper.cpp lit son adresse de base, `http://whisper:8080`, dans la configuration avec
  `ServiceUrl::fromString()`, puis y résout `/inference`.
- Un client d'un moteur de rendu PDF, comme le Gotenberg à `http://gotenberg:3000` auquel `phil/paged-media` envoie ses
  documents, lit son adresse de base de la même façon, puis y résout `/forms/chromium/convert/html`.

Une application Symfony construit un seul `ServiceHosts` à partir d'un paramètre — `%env(csv:SERVICE_HOSTS)%`, par
exemple — et le partage : la liste est immuable et ne contient rien d'une requête.

<!-- phil-dev source-sha256=5ca01c155afd2e54a6b65ec5ac028883e45a262ad53257c0777c8cd6c2eaddfc -->
# Lecture

## Le client

```php
$client = new SafeHttpClient(
    client: $httpClient,         // the transport; HttpClient::create() by default
    limits: new Limits(),        // what one read may cost
    resolve: [],                 // fixed addresses for host names, as curl's --resolve
);
```

Le client enveloppe le transport dans le `NoPrivateNetworkHttpClient` de Symfony ; quel que soit le transport qu'on lui
donne, la garde demeure. Il ne garde rien d'une lecture pour la suivante, sauf les adresses que la garde a résolues,
que `reset()` oublie : un *worker* l'appelle entre deux messages, comme `kernel.reset` le fait dans Symfony.

## Les limites

| Limite | Valeur par défaut | Bornes | Ce qu'elle borne |
|---|---|---|---|
| `timeout` | 5.0 | 0,1 à 60 secondes | Le silence de l'hôte, jamais plus que ce qui reste de la durée totale |
| `maxDuration` | 10.0 | 0,1 à 120 secondes | La lecture entière, chaque redirection comprise |
| `maxBytes` | 1,048,576 | 1 Kio à 64 Mio | Le corps conservé, après décompression |
| `maxRedirects` | 3 | 0 à 10 | Les redirections suivies |
| `userAgent` | `PhilSafeHttp/0.1` | Une ligne, aucun caractère de contrôle | Le `User-Agent` de chaque requête |
| `maxHeaderBytes` | 65,536 | 1 Kio à 1 Mio | Les en-têtes d'une réponse, chaque ligne comptée, lignes de statut comprises |
| `maxHeaders` | 100 | 1 à 1 000 | Les lignes d'en-tête d'une réponse, lignes de statut exclues |

Des limites hors de leurs bornes lancent `InvalidLimits`. Donnez au `User-Agent` le nom du site et un moyen de joindre
la personne qui l'exploite, comme le font les robots d'aperçu de lien : un site qui voit un lecteur inconnu le bloque.

## Les en-têtes

Les en-têtes d'une réponse sont comptés à mesure que le transport les signale, par sa progression, puis une fois de
plus quand ils sont tous arrivés. Au-delà de `maxHeaderBytes` ou de `maxHeaders`, le transfert est interrompu et la
lecture lance `FetchFailed`. Le corps est borné dans le même esprit : un morceau est coupé aux octets qui restent de
`maxBytes` avant de rejoindre le corps, de sorte que le corps conservé ne dépasse jamais la limite, quelle que soit la
taille du morceau que remet le transport.

Ce que chaque transport de Symfony HttpClient borne de lui-même, avant ces limites :

| Transport | Bornes propres | Quand les limites du paquet s'appliquent |
|---|---|---|
| curl (`CurlHttpClient`) | libcurl refuse une ligne d'en-tête de plus de 100 Kio (`CURL_MAX_HTTP_HEADER`) et, dans ses versions récentes, une réponse dont les en-têtes dépassent 300 Kio au total ou 5 000 lignes | Pendant la réception, à chaque progression du transfert |
| natif (`NativeHttpClient`) | Aucune sur le nombre ni le total : l'enveloppe de flux `http` de PHP garde chaque ligne, de toute longueur ; seules la ligne de statut (127 octets) et `Location` (8 182 octets) sont bornées | Une fois que l'enveloppe de PHP a lu tous les en-têtes : le silence et la durée totale bornent encore le temps que cela prend |
| amphp (`AmpHttpClient`) | La limite de taille des en-têtes d'amphp/http-client, laissée à sa valeur par défaut de 16 Kio ; Symfony lève sa limite de taille du corps | Une fois les en-têtes arrivés |

Symfony HttpClient ne borne ni le nombre ni les octets des en-têtes, et garde chaque ligne deux fois : brute dans
l'information `response_headers`, et par nom.

## La règle d'une lecture

`ReadRule` dit ce qui est lu du corps :

| Champ | Valeur par défaut | Rôle |
|---|---|---|
| `accept` | `*/*` | L'en-tête `Accept`, sur une ligne |
| `types` | aucun | Les types de média dont le corps est lu, en minuscules ; tous si vide, les autres ne sont pas lus |
| `stopAfter` | null | Une marque après laquelle le transfert s'arrête, trouvée sans égard à la casse, même coupée entre deux morceaux |

`ReadRule::htmlHead()` lit le HTML et le XHTML jusqu'à `</head`, pour les étiquettes d'une page.

## Lire

| Appel | Lit |
|---|---|
| `get($url, $rule)` | Le statut, les en-têtes et le corps que la règle lit |
| `head($url)` | Le statut et les en-têtes, sans corps |
| `send($method, $url, $headers, $body, $rule)` | Une méthode quelconque, avec ses en-têtes et son corps : le statut, les en-têtes, et le corps que la règle lit |

`$url` est un `Phil\Url\Url`, ou une chaîne que la garde lit. Une redirection — `301`, `302`, `303`, `307` ou `308` avec
un `Location` — est suivie avec la même méthode, sauf si elle quitte HTTPS pour HTTP : cette redirection lève
`RefusedAddress`. Un autre `3xx`, ou un `3xx` sans `Location`, est la réponse.

## Envoyer

`send()` fait une requête d'une méthode quelconque sous les gardes de `get()` : la première adresse et chaque
redirection vérifiées, les réseaux privés refusés, le silence, la durée totale, les redirections et les octets de la
réponse bornés par les limites. C'est l'appel pour un point de terminaison de jeton, le service Web d'une autre
plateforme, un webhook à envoyer.

```php
$response = $client->send(
    'POST',
    'https://platform.example/oauth/token',
    ['Authorization' => 'Basic '.base64_encode($id.':'.$secret), 'Content-Type' => 'application/x-www-form-urlencoded'],
    http_build_query(['grant_type' => 'client_credentials', 'scope' => $scope]),
    new ReadRule('application/json', ['application/json']),
);
```

Ce qu'il vérifie avant toute requête, avec `InvalidRequest` :

| Partie | Règle |
|---|---|
| La méthode | Des lettres seulement, mises en majuscules par le client, 20 au plus, comme HttpClient de Symfony les prend ; `CONNECT`, qui ouvre un tunnel, et `TRACE`, qui renvoie la requête et ses identifiants de connexion, sont refusés |
| Le nom d'un en-tête | Un jeton de la RFC 9110, pas fait de chiffres seulement — PHP garde un tel nom comme clé entière, que HttpClient de Symfony lit comme une ligne d'en-tête entière ; jamais `Accept`, que la règle donne, ni `User-Agent`, que les limites donnent, ni un en-tête de la connexion ou du découpage du message — `Host`, `Content-Length`, `Transfer-Encoding`, `Connection`, `Keep-Alive`, `Upgrade`, `TE`, `Trailer`, `Expect` — ni aucun en-tête `Proxy-` |
| La valeur d'un en-tête | Une ligne, non vide, aucun caractère de contrôle |
| Les en-têtes | `maxHeaderBytes` et `maxHeaders` des limites, chacun compté comme la ligne `Name: value` |
| Le corps | `maxBytes` des limites |

Le message d'une `InvalidRequest` nomme un en-tête, jamais sa valeur.

### Les redirections d'une requête

Seule une requête de `GET`, `HEAD` ou `OPTIONS` sans corps suit les redirections, chacune vérifiée comme pour `get()`.
Toute autre requête — un `POST`, un `PUT`, un `DELETE`, ou un `GET` avec un corps — n'en suit aucune : une redirection
répondue à celle-ci lance `RefusedRedirect`, de sorte qu'un corps n'est jamais envoyé deux fois, ni à un hôte auquel il
n'était pas destiné. Un autre `3xx`, ou un `3xx` sans `Location`, est la réponse, comme pour `get()`.

### Les en-têtes et les identifiants de connexion

Les en-têtes donnés à `send()` vont seulement au schéma, à l'hôte et au port de la première adresse. Une redirection
vers la même origine les porte ; une redirection ailleurs — un autre hôte, un sous-domaine, un autre port, HTTP vers
HTTPS — n'en porte aucun, seulement l'en-tête `Accept` de la règle et le `User-Agent` des limites. Un `Authorization`,
un `Cookie` ou une clé d'API n'atteint jamais un hôte que la requête n'a pas nommé.

Le client n'écrit aucun journal, et aucun de ses messages ne nomme la valeur d'un en-tête : les exceptions nomment
l'adresse, la méthode et le statut. Un transport donné au client garde son propre journaliseur, s'il en a un ; les
journaux de HttpClient de Symfony nomment la méthode et l'adresse d'une requête, jamais ses en-têtes.

## La réponse

`SafeResponse` :

| Membre | Contient |
|---|---|
| `url` | L'adresse qui a répondu, après les redirections |
| `redirects` | Les adresses vers lesquelles on a été redirigé, dans l'ordre, la dernière étant `url` |
| `status`, `isSuccessful()` | Le statut ; s'il est `2xx` |
| `headers`, `header($name)` | Les en-têtes par nom en minuscules ; la première valeur de l'un d'eux |
| `mediaType()`, `charset()` | Le type de média de `Content-Type` en minuscules, et son jeu de caractères tel qu'écrit, lus par des classes ASCII quelle que soit la locale du processus |
| `body` | Le corps dans la mesure où il a été lu |
| `complete` | Si le corps a été lu jusqu'à sa fin : faux s'il a été coupé à `maxBytes` ou après la marque d'arrêt, ou s'il n'a pas été lu |

## Les exceptions

Toutes implémentent `SafeHttpException`.

| Exception | Quand |
|---|---|
| `RefusedAddress` | L'adresse ou une redirection n'est pas une adresse HTTP ou HTTPS d'un nom d'hôte, une redirection quitte HTTPS pour HTTP, ou son hôte est un réseau privé ou s'y résout |
| `TooManyRedirects` | Les redirections dépassent `maxRedirects` |
| `FetchFailed` | L'hôte ne répond pas, reste silencieux plus longtemps que `timeout`, la lecture dépasse `maxDuration`, les en-têtes dépassent `maxHeaderBytes` ou `maxHeaders`, ou la connexion se rompt |
| `RefusedRedirect` | Une requête avec un corps, ou d'une méthode autre que `GET`, `HEAD` ou `OPTIONS`, a reçu une redirection pour réponse |
| `InvalidRequest` | Une méthode, un en-tête ou un corps que `send()` refuse, avant toute requête |
| `InvalidLimits` | Des limites, ou un en-tête d'une règle, hors de leurs bornes |

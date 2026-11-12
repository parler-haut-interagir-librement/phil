<!-- phil-dev source-sha256=7dcf4286e7af0d62fe2193b01f532708db562dbcecb8314a8a7f76fbfb0bd85b -->
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

Des limites hors de leurs bornes lancent `InvalidLimits`. Donnez au `User-Agent` le nom du site et un moyen de joindre
la personne qui l'exploite, comme le font les robots d'aperçu de lien : un site qui voit un lecteur inconnu le bloque.

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

`$url` est un `Phil\Url\Url`, ou une chaîne que la garde lit. Une redirection — `301`, `302`, `303`, `307` ou `308` avec
un `Location` — est suivie avec la même méthode ; un autre `3xx`, ou un `3xx` sans `Location`, est la réponse.

## La réponse

`SafeResponse` :

| Membre | Contient |
|---|---|
| `url` | L'adresse qui a répondu, après les redirections |
| `redirects` | Les adresses vers lesquelles on a été redirigé, dans l'ordre, la dernière étant `url` |
| `status`, `isSuccessful()` | Le statut ; s'il est `2xx` |
| `headers`, `header($name)` | Les en-têtes par nom en minuscules ; la première valeur de l'un d'eux |
| `mediaType()`, `charset()` | Le type de média de `Content-Type` en minuscules, et son jeu de caractères tel qu'écrit |
| `body` | Le corps dans la mesure où il a été lu |
| `complete` | Si le corps a été lu jusqu'à sa fin : faux s'il a été coupé à `maxBytes` ou après la marque d'arrêt, ou s'il n'a pas été lu |

## Les exceptions

Toutes implémentent `SafeHttpException`.

| Exception | Quand |
|---|---|
| `RefusedAddress` | L'adresse ou une redirection n'est pas une adresse HTTP ou HTTPS d'un nom d'hôte, ou son hôte est un réseau privé ou s'y résout |
| `TooManyRedirects` | Les redirections dépassent `maxRedirects` |
| `FetchFailed` | L'hôte ne répond pas, reste silencieux plus longtemps que `timeout`, la lecture dépasse `maxDuration`, ou la connexion se rompt |
| `InvalidLimits` | Des limites, ou un en-tête d'une règle, hors de leurs bornes |

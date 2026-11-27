<!-- phil-dev source-sha256=7b90b3dee13b2f39ce8c19fd9a31d053a3183fe6fc5150e3a95b10f90264dc41 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/safe-http` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.2.3-alpha.

### Sécurité

- Une redirection d'une adresse HTTPS vers une adresse HTTP est refusée avec `RefusedAddress::downgrade()`, quels que
  soient son statut (`301`, `302`, `303`, `307`, `308`) et son hôte : les en-têtes, le corps qu'une `307` ou une `308`
  envoie de nouveau, et la réponse voyageraient en clair, là où on peut les lire ou les changer. Une redirection de HTTP
  vers HTTPS, ou qui garde son schéma, est encore suivie.

## 0.2.2-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/http-client-contracts` 3.8, `symfony/service-contracts` 3.8).

## 0.2.1-alpha - 2026-11-15

### Ajouté

- `SafeHttpClient::send($method, $url, $headers, $body, $rule)` : une requête de n'importe quelle méthode, avec ses
  en-têtes et son corps, sous les gardes de `get()` — chaque adresse vérifiée, les réseaux privés refusés, le temps, les
  redirections et les octets de la réponse bornés. La méthode est faite de lettres seulement, mise en capitales ;
  `CONNECT` et `TRACE` sont refusées. Le nom d'un en-tête est un jeton, jamais fait de chiffres seulement, que PHP garde
  comme clé entière, jamais `Accept`, `User-Agent`, un en-tête de la connexion ou du découpage, ni un en-tête `Proxy-` ;
  sa valeur tient sur une ligne ; les en-têtes sont tenus à `maxHeaderBytes` et à `maxHeaders`, le corps à `maxBytes`.
  Seule une requête `GET`, `HEAD` ou `OPTIONS` sans corps suit les redirections ; les en-têtes donnés ne quittent jamais
  le schéma, l'hôte et le port de la première adresse. Aucun message ne nomme la valeur d'un en-tête.
- `InvalidRequest`, refusée avant toute requête, et `RefusedRedirect`, une redirection répondue à une requête qui n'en
  suit aucune.
- L'envoi d'une requête avec des en-têtes et un corps, ses redirections et ses identifiants, dans `doc/reading.md`.

### Corrigé

- `SafeResponse::mediaType()`, `mediaTypeOf()` et `charset()` lisent par classes ASCII : avec `LC_CTYPE` réglé à une
  locale UTF-8, PCRE prenait des octets hors ASCII pour des lettres et des espaces, et `text/h\xE9ml` était un type de
  média.

## 0.2.0-alpha - 2026-11-14

### Ajouté

- `Limits::$maxHeaderBytes`, 64 Kio par défaut, et `Limits::$maxHeaders`, 100 par défaut : les en-têtes d'une réponse,
  comptés comme le transport les rapporte et une fois de plus quand ils sont tous arrivés ; au-delà de l'une ou de
  l'autre limite, le transfert est interrompu et la lecture lance `FetchFailed`.
- `FetchFailed::headersTooLarge()`.
- Les bornes que chaque transport de Symfony HttpClient met de lui-même sur les en-têtes, dans `doc/reading.md`.

### Modifié

- Une réponse dont les en-têtes dépassent 64 Kio ou 100 en-têtes échoue désormais avec `FetchFailed::headersTooLarge()`,
  là où elle était lue ; relevez `Limits::$maxHeaderBytes` ou `Limits::$maxHeaders` pour la lire de nouveau.

### Corrigé

- Un morceau plus grand que les octets qui restent est coupé avant de rejoindre le corps : le corps gardé ne dépasse
  jamais `maxBytes`, alors qu'il pouvait le dépasser d'un morceau du transport. Le corps retourné et les erreurs ne
  changent pas.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `SafeHttpClient` : `get()` et `head()` derrière le `NoPrivateNetworkHttpClient` de Symfony, quel que soit le
  transport, les redirections suivies une à une et chacune vérifiée, le silence et la durée totale bornés à travers les
  redirections, le corps lu par morceaux jusqu'à ses octets ou une marque d'arrêt, aucun mandataire de l'environnement ;
  `resolve`, les adresses fixes encore vérifiées ; `reset()`.
- `Limits` : le silence, la durée totale, les octets, les redirections et le `User-Agent`, chacun borné.
- `ReadRule` : l'`Accept`, les types de médias dont le corps est lu et la marque d'arrêt ; `htmlHead()`.
- `SafeResponse` : l'adresse qui a répondu, les redirections, le statut, les en-têtes, le type de média, le jeu de
  caractères, le corps et s'il est complet.
- `AddressGuard` : une première adresse et une redirection lues par phil/url — une adresse HTTP ou HTTPS d'un nom
  d'hôte, aucune adresse IP, aucun identifiant —, une redirection résolue d'après l'adresse qui a répondu.
- `SafeHttpException`, `RefusedAddress`, `TooManyRedirects`, `FetchFailed` et `InvalidLimits`.
- La cible de fuzzing de la garde.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

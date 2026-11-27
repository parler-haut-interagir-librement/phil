<!-- phil-dev source-sha256=365380cec30251243410dfe9318c919eada71797df49456bf1c71012ebc54548 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/url` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.5-alpha.

### Modifié

- `ext-intl` n'est plus exigé, seulement suggéré : `symfony/polyfill-intl-idn` fournit `idn_to_ascii()` et
  `idn_to_utf8()` quand il n'est pas chargé. Sans `ext-intl`, un hôte international est converti environ 15 fois plus
  lentement, et `display()` le montre en ASCII, `xn--bcher-kva.example`, puisque aucun détecteur d'usurpation ne peut
  se porter garant de ses lettres ; avec lui, rien ne change.
- Un hôte fait de lettres ASCII minuscules, de chiffres et de traits d'union saute la conversion IDNA :
  `Url::fromString()` le vérifie comme avant, une étiquette avec des traits d'union en troisième et quatrième
  position, `ab--shop.example`, toujours refusée, et `display()` n'exécute plus sur lui le détecteur d'usurpation,
  environ trois fois plus vite.

## 0.1.4-alpha - 2026-11-15

### Ajouté

- `ServiceUrl` et `ServiceHosts` : l'adresse d'un service que l'application fait tourner à côté d'elle, dont l'hôte
  est un nom sans domaine ou une adresse IP — `http://whisper:8080`, `http://10.0.0.5:9200` —, lue par
  `ServiceUrl::fromString($text, $hosts)` seulement pour un hôte que l'application déclare dans `ServiceHosts` ; tout
  autre hôte est refusé, un hôte public compris. Les autres règles d'`Url` tiennent. `resolve()` résout une référence
  sur les mêmes schéma, hôte et port et refuse celle qui en sort ; `origin()` donne le schéma, l'hôte et le port.
  `Url::fromString()` continue de refuser ces hôtes.
- `InvalidUrl::serviceHost()`, `InvalidUrl::tooManyServiceHosts()`, `InvalidUrl::undeclaredServiceHost()` et
  `InvalidUrl::leavesService()`.
- `doc/service-addresses.md`, et la cible de fuzzing `url-service-addresses`.

### Corrigé

- `Url::fromString()` lit une adresse de la même façon quelle que soit la locale du processus. Avec `LC_CTYPE` réglé
  à `C.UTF-8` ou à une autre locale UTF-8, PCRE prenait l'octet 0xA0 pour une espace, si bien qu'une adresse
  contenant `à`, dont l'UTF-8 finit par cet octet, `https://example.com/voilà`, était refusée ; l'espace blanc est
  désormais l'espace blanc ASCII, et chaque motif de la bibliothèque nomme ses octets.

## 0.1.3-alpha - 2026-11-11

### Ajouté

- `Url::fromString($text, allowLocalhost: true)` accepte `localhost` et les noms sous `.localhost` (RFC 6761), pour
  le développement et les tests ; une adresse IP et tout autre nom seul restent refusés, `http://127.0.0.1/`,
  `http://localhostx/` et `http://intranet/` parmi eux.

### Modifié

- Par défaut, un nom sous `.localhost`, `http://app.localhost/`, est refusé comme `localhost` : les deux nomment
  l'adresse de bouclage.

## 0.1.2-alpha - 2026-11-11

### Modifié

- `Url::fromString()` lit une adresse comme le fait un navigateur, sur l'extension URI de PHP 8.5,
  `Uri\WhatWg\Url`, selon la norme URL du WHATWG : dans le chemin, une barre oblique inverse est une barre oblique et
  les segments `.` et `..` sont résolus, écrits en clair ou échappés (`%2e`, `%2E`, `.%2e`, `%2e.`) ; les caractères
  que le chemin, la requête ou le fragment ne peuvent pas contenir, `<`, `>`, `"` et les lettres hors ASCII parmi
  eux, sont encodés en pourcentage, chaque partie selon son propre ensemble de la norme. Un échappement déjà écrit
  reste tel qu'écrit, `%2F` un caractère de son segment et jamais un séparateur. Ses propres règles restent : `http`
  et `https` seulement, ni nom d'utilisateur ni mot de passe, un vide compris (`https://@example.com/`), aucun port
  0, aucune adresse IP, les règles d'hôte de 0.1.1-alpha. Les 2048 caractères au plus valent aussi pour l'adresse
  normalisée. Une adresse IPv4 écrite courte, `http://127.1/`, est nommée `127.0.0.1` dans le message d'`InvalidUrl` ;
  un domaine de premier niveau fait de chiffres, `https://shop.123/`, est refusé comme n'étant pas une adresse web,
  pas comme n'ayant pas de nom d'hôte.
- `Url::display()` montre comme lettres les lettres échappées du chemin et de la requête, `example.com/thé` ; un
  échappement d'ASCII, un caractère invisible, une espace ou un caractère de contrôle reste échappé.
- `phil/url` exige `ext-uri`, fournie avec PHP 8.5.

### Sécurité

- `path`, `segments()` et `equals()` donnent désormais le chemin qu'ouvre un navigateur :
  `https://example.com/docs/../admin`, `/docs/%2e%2e/admin` et `/docs\..\admin` ont le chemin `/admin`, si bien
  qu'une application qui permet des chemins par leur préfixe, `/docs/`, ne peut plus être contournée. `<`, `>` et `"`
  sont encodés en pourcentage dans `toString()`.

## 0.1.1-alpha - 2026-11-11

### Corrigé

- `Url::fromString()` lance `InvalidUrl`, et non la `ValueError` d'ICU, pour un hôte fait seulement de points —
  `https://.`, `https://...`, `https://./path` — et refuse un hôte qui finit par deux points, `https://example.com../`,
  dont la dernière étiquette est vide ; un seul point final est encore retiré.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Url` : `Url::fromString()` refuse un hôte qui est une adresse IP — un domaine de premier niveau fait de chiffres —
  et le port 0 ; `Url::display()` montre un hôte international dans ses propres lettres, `bücher.example`, à moins que
  le détecteur d'usurpation d'ICU le trouve suspect, `аpple.com` en cyrillique, qui reste en ASCII.
- `UrlType`, `KnownUrlType`, `TypedUrl` et `UrlTypes` ; `KnownUrlType::Mastodon` n'accepte aucune adresse sur les
  domaines des autres sortes, ni sur `medium.com`, `substack.com`, `figma.com`, `vsco.co`, `behance.net`, `ko-fi.com`
  et `buymeacoffee.com`, dont les profils s'écrivent aussi `/@name`.
  `KnownUrlType::Pinterest` n'accepte que les domaines que détient Pinterest, jamais un autre `pinterest.<tld>`.
  `KnownUrlType::YouTube` accepte aussi `youtube-nocookie.com`.
- `CookielessVideo`, qui change une vidéo YouTube en son lecteur sur `youtube-nocookie.com`, son moment de départ
  gardé, et une vidéo Vimeo en son lecteur avec `dnt=1`.
- `UrlException` et `InvalidUrl`, avec `InvalidUrl::port()`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.

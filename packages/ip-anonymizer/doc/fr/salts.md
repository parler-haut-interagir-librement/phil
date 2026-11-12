<!-- phil-dev source-sha256=8ca62be6a12a60c33e81039894ea71ca3f7a6a335954573189677569515ba0ad -->
# Sels

Une empreinte à clé n'est privée que dans la mesure où son sel est secret : une empreinte sans clé d'une adresse IPv4
s'inverse en calculant l'empreinte de ses 2^32 valeurs, quelques secondes de travail. Le sel est ce qui rend cette
énumération impossible à qui lit les empreintes sans le détenir.

## `Salt`

```php
use Phil\IpAnonymizer\Salt;

Salt::of('2026-q4', $secretBytes);   // 16 to 64 bytes; 32 recommended
Salt::random('ephemeral');           // 32 bytes from random_bytes()
```

L'identifiant compte de 1 à 32 caractères parmi les lettres, les chiffres, `.`, `-` et `_`. Il est écrit en tête de
chaque empreinte, pour que les empreintes de deux sels ne soient jamais égales. `var_dump()` et les autres affichages de
débogage montrent `[redacted]` à la place des octets.

## `ForgottenSalt`

Le fournisseur à privilégier : un sel aléatoire par période de l'horloge, un nouveau chaque jour UTC par défaut, que
personne ne peut dériver de nouveau une fois sa période terminée :

```php
use Phil\IpAnonymizer\ForgottenSalt;
use Symfony\Component\Clock\NativeClock;

new ForgottenSalt($saltStore, new NativeClock());          // daily
new ForgottenSalt($saltStore, new NativeClock(), 3600);    // hourly
```

La première empreinte d'une période tire 32 octets aléatoires et les réclame dans
l'[`ExpiringSaltStore`](extension-points.md#expiringsaltstore) de l'application, pour le temps qui reste à la période :
un sel tiré à 23 h 59 UTC est conservé une minute. Chaque serveur lit le même sel dans le magasin. Quand l'entrée
expire, le sel disparaît : aucune clé ne permet de le dériver, si bien que personne, l'exploitant compris, ne peut relier
les empreintes de deux périodes.

Son identifiant est `<period seconds>-<index>`, `86400-20400` pour le 2025-11-08, compté en UTC quel que soit le fuseau
horaire de l'horloge. Le sel est lu dans le magasin à chaque empreinte et n'est jamais gardé dans le processus : un
worker de longue durée ne détient donc rien au-delà de la fin d'une période. Chaque empreinte coûte ainsi un appel au
magasin.

## `FixedSalt`

Toujours le même sel : pour un sel que l'application fait tourner elle-même (lu dans ses secrets et changé au
déploiement) et pour les tests. Les empreintes d'une adresse restent reliables tant que le sel vit.

```php
use Phil\IpAnonymizer\FixedSalt;

new FixedSalt(Salt::of('2026-q4', $secretBytes));
```

## `RotatingSalt`

Facultatif, pour une application où rien ne peut être stocké et où l'exploitant peut être digne de confiance pour
chaque période. Un sel par période de l'horloge, dérivé d'une clé maîtresse de 32 octets, un nouveau chaque jour UTC par
défaut :

```php
use Phil\IpAnonymizer\RotatingSalt;
use Symfony\Component\Clock\NativeClock;

new RotatingSalt($masterKey, new NativeClock());           // daily
new RotatingSalt($masterKey, new NativeClock(), 3600);     // hourly
```

Le sel d'une période est le résultat de `sodium_crypto_kdf_derive_from_key()` sur la clé maîtresse, avec comme
identifiant de sous-clé le nombre de périodes entières depuis l'époque Unix ; son identifiant est
`<period seconds>-<index>`, `86400-20400` pour le 2025-11-08. Chaque processus et chaque serveur dérive le même sel pour
la même période à partir de la même clé, sans rien stocker ni rien synchroniser. Les périodes sont comptées en UTC, quel
que soit le fuseau horaire de l'horloge.

Générez la clé maîtresse une seule fois, gardez-la avec les secrets de l'application et ne la journalisez jamais :

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

## Qui ne peut pas relier les valeurs

| Fournisseur de sel | Reliable d'une période à l'autre par |
|---|---|
| `ForgottenSalt` | personne, une fois la période terminée |
| `FixedSalt` | qui détient le sel, tant qu'il vit |
| `RotatingSalt` | qui détient la clé maîtresse : chaque sel passé peut être dérivé de nouveau |

Un sel dérivé empêche quiconque lit les empreintes (un lecteur de journaux, une sauvegarde, une exportation analytique)
sans détenir la clé maîtresse de relier celles d'hier à celles d'aujourd'hui. Le détenteur de cette clé peut tout de
même les relier : c'est pourquoi `ForgottenSalt` vient en premier.

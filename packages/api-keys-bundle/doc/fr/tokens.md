<!-- phil-dev source-sha256=9b504b43a9eba6ee63684208a6a335909a34399372ba635c69066e9ddf59f456 -->
# Jetons

## Le format

```text
acme_3kTMd9qXbW1z_Qx7bV0aLk2sR9uYc4mNp8wTe1hJg6dFo2B9fXk
└┬─┘ └────┬─────┘ └──────────────┬───────────────┘└─┬──┘
prefix  identifier             secret            checksum
```

| Partie | Ce que c'est |
|---|---|
| Préfixe | De 2 à 16 lettres minuscules et chiffres, tirés de la configuration : le nom que cherche un détecteur de secrets |
| Identifiant | 12 lettres et chiffres, tirés au hasard : il trouve la ligne et apparaît dans les listes; il n'est pas secret |
| Secret | 32 lettres et chiffres, chacun tiré par `random_int()` de la source sécurisée de `random_bytes()`, chaque caractère aussi probable que les autres : environ 190 bits |
| Somme de contrôle | Le CRC-32 de tout ce qui la précède, en 6 lettres et chiffres |

Chaque jeton d'un préfixe a la même longueur, et les traits de soulignement en font un seul mot pour un double-clic. La
somme de contrôle refuse un jeton mal tapé, tronqué ou complété avant toute recherche dans la base de données, et
permet à un détecteur de distinguer un vrai jeton d'une imitation. Elle ne protège rien : c'est le secret qui protège.

`TokenFormat::parse()` vérifie, dans l'ordre, la longueur, le préfixe et les séparateurs, les caractères et la somme de
contrôle, et lève `MalformedToken` avec une raison qui ne répète jamais le texte.

## Les propriétaires

Un jeton agit pour un propriétaire : un type et un identifiant, `TokenOwner`. Le type `user` est un membre, chargé par le
fournisseur d'utilisateurs du pare-feu à partir de l'identifiant donné ; une chaîne là où un propriétaire est attendu est
un membre. La configuration déclare les autres types — une organisation, une application, une équipe — avec le
fournisseur d'utilisateurs qui les charge et les portées que leurs jetons peuvent détenir (voir
[Configuration](configuration.md) et [Authentification et portées](authentication.md)).

```php
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

$tokens->create($user->getUserIdentifier(), 'Deploy script', ['projects:read']);       // a member
$tokens->create(new TokenOwner('organization', $organization->slug), 'Billing export', ['billing']);
```

Un type est une lettre minuscule suivie d'au plus 31 lettres minuscules, chiffres ou `_` ; un identifiant compte de 1 à
180 octets. Un membre et une organisation de même identifiant possèdent des jetons différents : toute recherche par
propriétaire compare le type et l'identifiant.

## Ce qui est stocké

La ligne garde l'identifiant, le type et l'identifiant du propriétaire, le nom, les portées, l'empreinte à clé du secret,
la création et l'expiration, la dernière utilisation, son adresse anonymisée et la révocation. Jamais le secret : une
copie de la table n'ouvre rien sans la clé.

L'empreinte à clé est le HMAC-SHA-256 de `<identifier>_<secret>` sous la première clé de `hash_key`, écrit selon la forme
du format de chaîne PHC pour qu'il nomme son algorithme et la version de ce qui est haché :

```text
$hmac-sha256$v=1$DBwoY14DqHXh4Kk5eij6KIEjRULz1gJAfldLl/i37UY
 └────┬────┘ └┬┘ └───────────────────┬─────────────────────┘
  algorithm  version   the 32 bytes in Base64, without padding
```

Une empreinte d'un autre algorithme ou d'une autre version ne correspond à rien : une version ultérieure se distingue de
celle-ci, et les lignes y passent à mesure que leurs jetons sont renouvelés.

### Les clés

`hash_key` est une liste ordonnée : la première clé calcule l'empreinte de chaque nouveau jeton, et chaque clé la vérifie,
de sorte que les jetons émis sous une clé précédente continuent de fonctionner tant qu'elle reste dans la liste. Pour
renouveler la clé, mettez la nouvelle en premier et gardez les précédentes après elle ; une fois la durée de vie maximale
écoulée depuis qu'une clé a reculé, aucun jeton émis sous elle n'est encore valide, et elle peut être retirée. Chaque clé
est calculée et comparée à chaque vérification, quelle que soit celle qui correspond, jusqu'à huit clés. L'empreinte est
la seule chose que le bundle associe à une clé : le CRC-32 du texte du jeton est une somme de contrôle, pas une signature, et
le point de terminaison de l'analyse de secrets de GitHub vérifie les signatures de GitHub sous les clés de GitHub. Le
texte d'un jeton existe une seule fois, dans l'`IssuedToken` que retourne sa création, qui refuse la sérialisation et
cache le texte à `var_dump()`.

## Créer

```php
use Phil\ApiKeysBundle\Application\ApiTokens;

$issued = $tokens->create($user->getUserIdentifier(), 'Deploy script', ['projects:read'], new DateTimeImmutable('+30 days'));

$issued->plainText;   // show it now, once
$issued->token->id(); // what the lists show
```

Sans expiration, le jeton dure `default_lifetime` jours. L'expiration doit être dans le futur et dans les
`max_lifetime` jours. Les portées doivent être déclarées, et sont gardées dans l'ordre de la configuration. Le nom
compte de 1 à 100 caractères, sans les espaces autour. `TokenCreated` est réparti une fois le jeton enregistré.

## Vérifier

`ApiTokens::verify()` est ce qu'appelle le gestionnaire de jetons. Il vérifie hors ligne la forme et la somme de contrôle,
puis cherche la ligne par son identifiant, compare le secret à l'empreinte sous chaque clé en temps constant (un
identifiant inconnu coûte les mêmes empreintes), puis vérifie la révocation et l'expiration. Un jeton refusé lève `InvalidCredentials`, dont
le `failure` indique la raison pour les journaux : `malformed`, `unknown`, `revoked` ou `expired`. Le client ne sait
jamais laquelle.

## La dernière utilisation

Un jeton utilisé de nouveau dans les `last_used_throttle` secondes n'écrit pas sa ligne : un script qui appelle
plusieurs fois par seconde écrit une fois par intervalle. Quand il l'écrit, la date et l'adresse (telle que la donne
l'anonymiseur configuré, ou rien) sont écrites par une seule instruction qui ne vide rien d'autre de l'unité de travail
de la requête, et `TokenUsed` est réparti.

## Révoquer

```php
$tokens->revoke($id, $owner); // the owner's token, or TokenNotFound
$tokens->revoke($id);         // any token, for administrators
$tokens->revokeAll(new TokenOwner('application', $app->id)); // every active token of an owner
```

Un jeton révoqué est refusé sur-le-champ et garde sa ligne, avec la date, jusqu'à la purge. Le révoquer de nouveau ne
change rien et ne répartit rien. `revokeAll()` révoque chaque jeton actif d'un propriétaire — une organisation qui ferme,
une application qui est désinstallée, un membre qui part —, jusqu'à dix mille en un appel, chacun annoncé par
`TokenRevoked`, et retourne combien elle en a révoqué.

## Renouveler

```php
$successor = $tokens->rotate($id, $owner);            // the old token revoked at once
$successor = $tokens->rotate($id, $owner, grace: 600); // the old token works ten more minutes
```

Le successeur a le même propriétaire, le même nom et les mêmes portées, et dure aussi longtemps que l'ancien jeton,
dans la limite de la durée de vie maximale. Un jeton révoqué ou expiré n'est pas renouvelé.

## Purger

`ApiTokens::purge($retention)` supprime les jetons expirés ou révoqués depuis plus de `$retention` jours; la commande
`phil:api-keys:purge-expired` l'exécute avec `purge_retention`.

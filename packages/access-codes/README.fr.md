<!-- phil-dev source-sha256=1520209aa769ee7d353756b822814472918c668bced8c8c15a5f4c4bae872ec3 -->
# phil/access-codes

**Codes à usage unique en lots nommés, donnés une seule fois à la création et conservés seulement sous forme d'empreintes à clé, échangés une seule fois.**

phil/access-codes crée des lots de codes — cartes de téléchargement vendues à un kiosque, codes de bon imprimés dans un
livre, clés remises à des contributeurs — et échange chaque code une seule fois. Un lot a un nom, une taille allant
jusqu'à un million et une expiration facultative. Ses codes sont tirés par le générateur sécurisé de PHP dans un
alphabet sans symboles qui se ressemblent, la Base32 de Crockford par défaut, avec un symbole de contrôle qui repère un
symbole mal saisi avant toute recherche : `7KQ2-M9XD-H4TB-H`. La longueur et l'entropie se choisissent, jamais sous
40 bits.

Les codes en clair n'existent qu'une fois : dans le `CreatedBatch` que renvoie `create()`, écrits une seule fois en CSV
— chaque code avec son lien d'échange prérempli — ou pris une seule fois sous forme de liste pour l'impression. Le
magasin conserve le HMAC-SHA-256 de chaque code sous la clé de l'application, une liste ordonnée de clés où la première
calcule l'empreinte et où chaque clé retrouve un code, de sorte que la clé change sans perdre un lot. Rien ne peut
montrer un code de nouveau.

Un échange dépense une tentative d'un limiteur de débit de Symfony, lit le code tel que saisi — en minuscules, avec des
traits d'union ou des espaces, `O` pour `0` —, puis l'échange par un seul `UPDATE` conditionnel : de deux échanges au
même instant, un seul l'emporte. La réponse dit si le code a été échangé et, sinon, pourquoi : inconnu, déjà échangé,
expiré, révoqué ou trop de tentatives. Un lot se révoque d'un coup, pour une boîte de cartes perdue, et se compte :
échangés, révoqués, inutilisés.

C'est une bibliothèque PHP simple, fondée sur `phil/url`, `psr/clock` et `symfony/rate-limiter`. Les lots vivent dans un
magasin : en mémoire pour les tests et les scripts, ou dans deux tables d'une connexion Doctrine DBAL sur PostgreSQL,
MySQL, MariaDB ou SQLite, testées sur MySQL 8.4 et PostgreSQL 17. Elle ne garde aucun état entre deux appels et
fonctionne dans un *worker*. La page d'échange et les formulaires appartiennent à l'application ou à un bundle ; la
bibliothèque fournit à la page ses liens préremplis et la réponse à afficher.

## État

| Partie | État |
|---|---|
| Alphabets sans symboles qui se ressemblent, lettres semblables lues comme leur chiffre, symbole de contrôle Luhn mod N, longueur et entropie au choix avec un plancher de 40 bits | Implémenté et testé |
| Lots nommés d'un million de codes distincts au plus, tirés du générateur sécurisé, avec une expiration | Implémenté et testé |
| Les codes donnés une seule fois : CSV avec liens préremplis, ou liste ; jamais sérialisés, clonés ni affichés | Implémenté et testé |
| HMAC-SHA-256 sous une liste ordonnée d'au plus huit clés : la première calcule l'empreinte, chaque clé retrouve | Implémenté et testé |
| Échange par un seul `UPDATE` conditionnel, résultats, tentatives bornées par un limiteur de débit de Symfony | Implémenté et testé |
| Révocation d'un lot, compteurs, personne qui échange nommée par un identifiant stable | Implémenté et testé |
| Magasin en mémoire ; magasin Doctrine DBAL, son schéma, huit processus en concurrence sur MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Cible de fuzzing du lecteur de codes ; banc d'essai d'un lot de 100 000 codes | Implémenté |
| Un bundle Symfony : services issus de la configuration, page et formulaire d'échange, commande de console qui exporte un lot | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la construction des services. |
| [Codes](doc/fr/codes.md) | `CodeAlphabet`, `CodeFormat` : l'alphabet, les symboles semblables, le symbole de contrôle, la longueur et l'entropie. |
| [Lots](doc/fr/batches.md) | La création d'un lot, le CSV et la liste uniques, les liens préremplis, la révocation, les compteurs. |
| [Échange](doc/fr/redemption.md) | `redeem()`, les tentatives, les résultats, l'atomicité, la personne qui échange. |
| [Clés et stockage](doc/fr/storage.md) | `CodeHasher` et ses clés ordonnées, les magasins, les tables. |
| [Exceptions](doc/fr/exceptions.md) | `AccessCodesException` et ses quatre exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et son banc d'essai, et comment tester du code qui échange. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/access-codes
```

```php
use Phil\AccessCodes\AccessCodes;
use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\InMemoryCodeStore;
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$codes = new AccessCodes(
    new InMemoryCodeStore(),
    new CodeHasher($_SERVER['ACCESS_CODES_KEY']),
    new RateLimiterFactory(['id' => 'redeem', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '15 minutes'], new InMemoryStorage()),
    new NativeClock(),
);

$created = $codes->create('Album cards, autumn fair', 500);
$created->writeCsv(new SplFileObject('cards.csv', 'x'), new RedemptionLinks(Url::fromString('https://shop.example/redeem')));

$redemption = $codes->redeem('7kq2-m9xd-h4tb-h', 'member-1842', $clientAddress);
$redemption->isRedeemed();   // true, and $redemption->batchId names what it unlocks
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring`, `phil/url`, `psr/clock` et `symfony/rate-limiter` 8.2 ou une
version plus récente ; `doctrine/dbal` 4.5 ou une version plus récente pour le magasin en base de données.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

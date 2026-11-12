<!-- phil-dev source-sha256=d8987faaf7b4b556f6ac8dc853bccd85572e87412bc6d11039f3b6b502b9d0cb -->
# phil/ip-anonymizer

**Des adresses IP anonymisées avant d'être conservées : tronquées, réduites à une empreinte avec un sel ensuite oublié,
ou généralisées.**

phil/ip-anonymizer transforme l'adresse d'un client en ce qu'une ligne de journal, un compteur de visites ou un limiteur
de débit peut conserver à sa place. Il lit strictement les adresses IPv4 et IPv6, lit une adresse IPv6 mappée sur IPv4
comme son adresse IPv4, et offre trois familles de stratégies :

- la **troncature** efface les bits d'hôte au-delà d'un préfixe propre à chaque famille (/24 et /48 par défaut), et le
  résultat se lit toujours comme une adresse ;
- le **hachage à clé** calcule l'empreinte BLAKE2b de l'adresse avec un sel comme clé, par défaut un sel aléatoire
  oublié à la fin de chaque jour UTC : une adresse donne la même valeur toute la journée, et des valeurs que personne ne
  peut relier d'un jour à l'autre ;
- la **généralisation** ne garde que le réseau en notation CIDR, la portée à usage particulier de l'adresse (`public`,
  `private`, `loopback`…), ou un libellé tiré d'une table de réseaux que l'application écrit (une région, un bureau),
  sans aucune base de géolocalisation.

Chaque stratégie implémente `IpAnonymizer`. `Pipeline` exécute des transformateurs d'adresse avant l'une d'elles
(l'empreinte d'une adresse tronquée compte un réseau au lieu d'un hôte), et `PerFamily` donne à IPv4 et à IPv6 chacun la
sienne.

C'est une simple bibliothèque PHP dont les prérequis sont `ext-sodium` et `psr/clock`. Chaque classe
est immuable et ne conserve aucun état entre deux appels : une seule instance sert donc toutes les requêtes d'un worker
de longue durée.

## État

| Partie | État |
|---|---|
| Analyse stricte d'IPv4 et d'IPv6, adresses mappées sur IPv4 lues comme IPv4, texte RFC 5952 sur toutes les plateformes | Implémenté et testé |
| Réseaux en notation CIDR, masques de préfixe, `contains()` | Implémenté et testé |
| Troncature selon le préfixe de chaque famille | Implémenté et testé |
| Empreintes BLAKE2b à clé ; sels aléatoires oubliés à la fin de leur période, sels fixes, sels dérivés d'une clé maîtresse | Implémenté et testé |
| Généralisation à un réseau, à une portée à usage particulier, à un libellé tiré d'une table de réseaux | Implémenté et testé |
| Compositions `Pipeline` et `PerFamily` | Implémenté et testé |
| Intégration à un cadriciel (un processeur de journal, un filtre de gabarit, une configuration) | Absente de cette bibliothèque : les adaptateurs tiennent en quelques lignes, montrées dans [Points d'extension](doc/fr/extension-points.md) |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le choix d'une stratégie. |
| [Adresses et réseaux](doc/fr/addresses.md) | `IpAddress`, ce que l'analyseur accepte et refuse, `IpNetwork`. |
| [Stratégies](doc/fr/strategies.md) | La troncature, le hachage à clé, les trois généralisations et leur composition. |
| [Sels](doc/fr/salts.md) | `Salt`, `ForgottenSalt`, `FixedSalt`, `RotatingSalt`, et qui peut relier les valeurs de deux périodes. |
| [Points d'extension](doc/fr/extension-points.md) | Vos propres stratégies, fournisseurs de sel et étiqueteurs de réseaux ; les adaptateurs de cadriciel. |
| [Exceptions](doc/fr/exceptions.md) | `IpAnonymizerException` et ses quatre exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation, ses cibles de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/ip-anonymizer
```

```php
use Phil\IpAnonymizer\ForgottenSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\Truncation;
use Symfony\Component\Clock\NativeClock;

$address = IpAddress::tryParse($request->getClientIp() ?? '');

if (null !== $address) {
    new Truncation()->anonymize($address);                 // '203.0.113.0'

    $daily = new KeyedHash(new ForgottenSalt($saltStore, new NativeClock()));
    $daily->anonymize($address);                           // '86400-20400:5f0c…', another value tomorrow
}
```

`$saltStore` est l'`ExpiringSaltStore` de l'application, un cache partagé avec expiration : voir [Sels](doc/fr/salts.md).

## Prérequis

PHP 8.5 ou plus récent, `ext-sodium` et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

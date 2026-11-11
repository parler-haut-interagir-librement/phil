<!-- phil-dev source-sha256=b1c5c01ddff1905f452ff596af2a0925d72b34dd849b32c9003a32f0304966e2 -->
# phil/url

**Des adresses Web comme des valeurs normalisées, avec leur genre détecté : Facebook, YouTube, Mastodon, un site Web.**

`phil/url` lit une adresse absolue `http` ou `https` en un `Url` normalisé : hôte en minuscules, port par défaut
retiré, noms d'hôte internationaux en punycode, et une forme `display()` telle que les gens la lisent. Les genres
d'adresse sont des cas d'enum qui implémentent `UrlType`, chacun avec sa propre règle : une page Facebook est une
adresse sur `facebook.com`. `KnownUrlType` contient 26 genres internationaux, de Facebook et YouTube jusqu'à Mastodon
sur n'importe quel serveur et à un simple site Web. `UrlTypes` détecte le genre d'une adresse parmi les ensembles
d'enums qu'un champ accepte, et `CookielessVideo` change une vidéo YouTube ou Vimeo en son lecteur qui n'écrit aucun
témoin de connexion. **Il ne fait pas** de requête vers les adresses, ne suit pas les redirections, ne vérifie
pas qu'une page existe et ne contient pas les genres d'une région.

Il s'adresse aux applications où des gens entrent des liens vers leurs profils et leurs sites : un répertoire, une page
de membre, une liste d'événements. Les genres d'une région, comme un registre des entreprises ou un portail
gouvernemental, appartiennent à l'application, dans sa propre enum, acceptée à côté de `KnownUrlType`. La détection
prend le premier cas qui accepte, ensemble par ensemble : les cas précis passent donc en premier et les cas fourre-tout
en dernier.

`phil/url` est une bibliothèque PHP simple basée sur `ext-intl`, `ext-mbstring` et `phil/enums`, dont l'`EnumSet` porte
les genres acceptés. Dans une application Symfony, utilisez `phil/url-bundle` : un champ de formulaire qui détecte le
genre, la contrainte `UrlOfType`, un type de colonne Doctrine et des filtres Twig. La conception repose sur des types
valeurs et des enums : un `TypedUrl` est une adresse avec son genre, et un genre est n'importe quel cas de n'importe
quelle enum qui dit s'il accepte une adresse.

## État

| Partie | État |
|---|---|
| `Url` : analyse, normalisation, hôtes internationaux, affichage | Implémenté et testé |
| `UrlType`, et `KnownUrlType` : 26 genres internationaux | Implémenté et testé |
| `TypedUrl`, et `UrlTypes` : détection du genre parmi des ensembles d'enums | Implémenté et testé |
| `CookielessVideo` : lecteurs YouTube et Vimeo sans témoins | Implémenté et testé |
| Exceptions sous une seule interface `UrlException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Adresses](doc/fr/addresses.md) | `Url` : ce qui est accepté, comment c'est normalisé et affiché. |
| [Genres](doc/fr/kinds.md) | `UrlType`, les genres internationaux, ceux de l'application, la détection parmi des ensembles d'enums, les lecteurs vidéo sans témoins. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/url
```

```php
use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\Url\UrlTypes;

$social = EnumSet::of(KnownUrlType::class, KnownUrlType::Facebook, KnownUrlType::Instagram);

$typed = UrlTypes::type('https://www.instagram.com/shop', $social);
$typed->type;              // KnownUrlType::Instagram
$typed->url->display();    // "instagram.com/shop"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl` et `ext-mbstring` ; `phil/enums` 0.3.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).

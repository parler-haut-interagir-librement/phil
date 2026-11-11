<!-- phil-dev source-sha256=89c4e58e9798879b9ad6e4956fd96ab7b13bc5b63917e23eb23ae8d0bc5c9b24 -->
# phil/resource

**Désignez n'importe quel enregistrement de votre application par son genre et son identifiant, comme `page:42`.**

phil/resource contient un seul type de valeur, `ResourceReference` : un genre et un identifiant, écrits
`kind:identifier`, comme `page:42` ou `event:0192f3c4-…`. C'est ce que garde une corbeille, ce que vise un index des
usages, ce qu'étiquette une taxonomie ou ce que déplace un calendrier, sans connaître la classe de la ressource. Une
référence qui enfreint les règles lève `InvalidResource`.

Elle s'adresse aux bundles qui désignent des ressources : une référence que l'un d'eux donne est une référence que les
autres acceptent. `phil/trash-bundle`, `phil/references-bundle`, `phil/taxonomy-bundle`,
`phil/scheduled-transitions-bundle` et `phil/calendar-bundle` la partagent. Une application qui a besoin d'un lien typé
vers une seule classe d'entité est mieux servie par une simple association Doctrine.

C'est une simple bibliothèque PHP, sans dépendance. `of()` construit une référence à partir de ses parties,
`fromString()` la relit, `toString()` l'écrit et `equals()` en compare deux.

## État

| Partie | État |
|---|---|
| `ResourceReference`, `InvalidResource` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Référence](doc/fr/reference.md) | Le type, ses règles, sa relecture à partir de sa chaîne. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données que l'application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |

## Démarrage rapide

```php
$page = ResourceReference::of('page', '42');
$page->toString();                                  // 'page:42'
ResourceReference::fromString('event:urn:uuid:1');  // kind 'event', identifier 'urn:uuid:1'
```

## Prérequis

PHP 8.5 ou plus récent ; rien d'autre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

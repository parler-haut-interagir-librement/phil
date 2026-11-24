<!-- phil-dev source-sha256=a8d3ebb3718e7bf426910cd37d5bb1577e155f14573965cc49e4c7f0250bdefe -->
# phil/newsletter-archive

**L'archive publique des infolettres envoyées d'un site : les numéros qu'un éditeur publie, chacun à une adresse propre
ou derrière un lien révocable, dans une version Web qui ne contient rien d'un abonné.**

phil/newsletter-archive est un bundle Symfony construit sur `phil/newsletter-bundle`. Une campagne envoyée n'entre dans
l'archive que lorsqu'un éditeur la publie : elle est alors listée, une page à la fois, filtrée par liste et par
étiquette, et lue à un slug tiré de son sujet. Un éditeur peut aussi partager une campagne par un lien, publiée ou non :
un jeton opaque dont l'archive ne garde que l'empreinte, révoqué en un appel, remplacé en partageant de nouveau. Dépublier
retire les deux.

La version Web est rendue une seule fois, à la publication du numéro, et conservée telle quelle. Elle est rendue avec des
données neutres à la place de celles de l'abonné : aucun lien de désabonnement ou de préférences, aucun jeton signé,
aucun lien suivi, aucun pixel espion, rien de ce qu'un style en ligne cache ; puis elle est purifiée par `phil/markup`.
Comme l'archive ne garde aucun renseignement personnel, effacer un abonné la laisse telle quelle.

Les pages sont de simples gabarits Twig, sans classe dont la page ait besoin, bien lus par la feuille de style de
phil-ux et remplaçables comme n'importe quel gabarit de bundle ; la page d'un numéro donne son adresse canonique ainsi
que son titre et sa description Open Graph. Une commande publie, dépublie, partage, révoque et change les étiquettes ;
`IssueFeed` donne les derniers numéros à un fil.

## État

| Partie | État |
|---|---|
| `ArchivedIssue`, `Tag`, `ArchiveFilter`, `IssuePage`, `ArchiveRepository` | Implémentés et testés |
| `Archive`, `ArchiveReader`, `NeutralWebVersion`, `LinkTokens`, `IssueFeed` | Implémentés et testés |
| `DbalArchiveRepository`, `ArchiveSchema`, `ArchiveSchemaListener` | Implémentés et testés sur SQLite |
| Les pages, leurs routes et gabarits, la commande, la recette Flex | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, les routes et les tables. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_newsletter_archive`. |
| [L'archive](doc/fr/archive.md) | Publier, dépublier, partager par un lien, étiquettes et slugs, par le code et par la ligne de commande. |
| [La version Web](doc/fr/web-version.md) | Comment un numéro est rendu avec des données neutres, purifié et figé ; ce que cela veut dire pour la vie privée. |
| [Pages](doc/fr/pages.md) | Les routes, les gabarits et leurs variables, l'adresse canonique, les en-têtes d'une page partagée. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/newsletter-archive
```

```yaml
# config/routes/phil_newsletter_archive.yaml
phil_newsletter_archive:
    resource: '@PhilNewsletterArchiveBundle/config/routes.php'
    prefix: /newsletter/archive
```

```sh
bin/console phil:newsletter-archive publish <campaign-id> --tag=events
bin/console phil:newsletter-archive share <campaign-id>
```

## Prérequis

PHP 8.5 ou une version plus récente, `phil/newsletter-bundle` 0.3 ou plus récent, Doctrine DBAL 4.5, Symfony 8.2 et
Twig 3.30.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

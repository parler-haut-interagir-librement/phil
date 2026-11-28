<!-- phil-dev source-sha256=42a64f69d0944a8df77b4b80ebfd21a1021413820a6cbc669058b73033873f6d -->
# phil/shifts-bundle

**Les quarts de bénévolat dans Symfony : listes et tableau d'échange stockés avec Doctrine, pages de bénévoles et de coordonnateurs, formulaire de quart, composants Twig, Messenger.**

phil/shifts-bundle apporte phil/shifts à une application Symfony. Il stocke les **quarts**, leurs **listes** et les
offres du **tableau d'échange** avec Doctrine DBAL, ses tables s'ajoutant au schéma que génère l'ORM, de sorte que
`doctrine:migrations:diff` en écrit la migration. Une écriture se fait sur la révision lue : deux requêtes qui modifient
en même temps la même liste ne s'écrasent jamais, et une place passée par le tableau d'échange s'écrit avec son offre
dans une seule transaction.

Les **pages des bénévoles** énumèrent les quarts à venir, ceux qui sont critiques et manquent de bénévoles en premier, et
permettent à un bénévole de s'inscrire, de rejoindre la réserve, de se dire intéressé, de se désister, d'offrir sa place
et d'en prendre une offerte. Un bénévole **n'occupe jamais deux quarts fixes qui se chevauchent**, avec le repos que la
configuration demande entre eux. Les **pages des coordonnateurs** planifient et corrigent un quart avec un **formulaire
Symfony** dans le fuseau horaire du site, affectent et retirent des bénévoles dans tous les modes, attribuent les places
parmi les intéressés — par **Messenger** quand l'application a un bus — et approuvent ou refusent les échanges qui
attendent. Chaque page est un simple formulaire Twig qui fonctionne sans JavaScript, avec un jeton CSRF et les classes
du kit PHIL UX.

Les **composants Twig** `<twig:PhilShifts:ShiftCard>` et `<twig:PhilShifts:MyShifts>` montrent un quart avec les boutons
de la personne connectée, et les quarts de cette personne avec les heures servies, sur n'importe quelle page de
l'application. Des **voters** décident qui est bénévole et qui coordonne. Les événements de chaque changement, qui ne
portent que des identifiants, vont au répartiteur d'événements de l'application une fois le changement stocké.
`phil:shifts:import` importe un plan dans le format `phil-shifts/1`.

Deux règles facultatives gardent l'accès aux quarts. Un bénévole **mineur** le jour d'un quart, selon la date de
naissance que donne l'application, le rejoint une fois enregistré le contact d'un parent ou d'un tuteur avec son
consentement. Un coordonnateur enregistre les **absences non annoncées** ; au-delà d'un seuil configuré, un bénévole ne
s'inscrit plus de lui-même tant qu'un organisateur n'a pas levé le blocage.

Les quarts, les offres, le contact du tuteur et les absences d'un bénévole sont **exportés et effacés** par les contrats
de phil/privacy-bundle. Une recette Flex enregistre le bundle, sa configuration et ses routes.

## État

| Partie | État |
|---|---|
| Les tables, l'écouteur de schéma, les dépôts avec leurs révisions, la transaction d'un échange | Implémenté et testé |
| Les pages des bénévoles, les conflits entre quarts fixes, le tableau d'échange | Implémenté et testé |
| Les pages des coordonnateurs, le formulaire d'un quart, l'attribution par Messenger | Implémenté et testé |
| Les composants `PhilShifts:ShiftCard` et `PhilShifts:MyShifts` | Implémenté et testé |
| Les voters, les jetons CSRF, les événements, la commande `phil:shifts:import` | Implémenté et testé |
| Les tuteurs des bénévoles mineurs, les absences non annoncées et leur seuil | Implémenté et testé |
| L'export et l'effacement de phil/privacy-bundle | Implémenté et testé |
| La recette Flex | Préparée dans `recipe/0.1` |
| Rappels avant un quart, un fil iCalendar personnel | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, Security, la migration et un premier quart. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les rôles, et ce que le bundle enregistre. |
| [Pages et composants](doc/fr/pages-and-components.md) | Les pages des bénévoles et des coordonnateurs, le formulaire, les composants, l'accessibilité. |
| [Mineurs et absences](doc/fr/minors-and-absences.md) | Le consentement d'un tuteur pour un bénévole mineur, les absences non annoncées et leur seuil. |
| [Stockage et vie privée](doc/fr/storage-and-privacy.md) | Les tables, les révisions, la migration, les événements, Messenger, et les données d'un bénévole. |
| [Points d'extension](doc/fr/extension-points.md) | Les services qu'une application remplace, et ce qui est stable. |
| [Tests](doc/fr/testing.md) | Les tests et l'application dans laquelle ils s'exécutent. |
| [Référence](doc/fr/reference.md) | Chaque type public et chaque route, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/shifts-bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console phil:shifts:import plan.json
```

```twig
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
<twig:PhilShifts:MyShifts limit="5" />
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring`, Symfony 8.2 avec FrameworkBundle, Security, Forms et Twig, et
Doctrine DBAL 4. L'ORM, Messenger, Console, les composants Twig et phil/privacy-bundle sont utilisés quand ils sont
installés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).

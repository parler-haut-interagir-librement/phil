<!-- phil-dev source-sha256=c44121cf50d89e8387ddb91682634dfc75be6eaeae45815e3dd9a8b9bde0e0db -->
# phil/doctrine-behaviours-bundle

**Horodatage, traçabilité de l'auteur, suppression douce, arbres et huit autres comportements, activés par
configuration dans une application Symfony.**

phil/doctrine-behaviours-bundle intègre `phil/doctrine-behaviours` à une application Symfony. Il enregistre les
écouteurs des comportements qu'active la configuration auprès du gestionnaire d'événements de DoctrineBundle, le lecteur
`Tree` comme service, et les filtres `phil_soft_deleted` et `phil_deactivated`, activés sur le gestionnaire d'entités
par défaut. La traçabilité de l'auteur nomme l'utilisateur qu'a authentifié Symfony Security, lorsque le bundle de
sécurité est installé. Les positions sont activées, avec le service `Positions`, et les traductions aussi, avec le service
`Translations`. La propriété, la publication par
workflow, les *slugs*, les versions et les identifiants datés s'activent un par un. Avec `phil/privacy-bundle` installé,
l'auteur, le propriétaire et les versions répondent à ses demandes d'accès et d'effacement, sur chaque gestionnaire
d'entités, et aucun nom n'est gardé comme auteur pendant que ses outils d'effacement s'exécutent.

Il s'adresse à une application Symfony sur Doctrine ORM qui veut ces comportements sans brancher des écouteurs à la
main. Un comportement qui n'a besoin de rien de plus est activé, un comportement qui a besoin d'un paquet ou d'un réglage
est désactivé jusqu'à ce qu'on le demande, et chacun peut être désactivé. Une application avec plusieurs gestionnaires
d'entités désactive le filtre ici et l'ajoute aux gestionnaires qui en ont besoin. Une application qui vient des
extensions Gedmo trouve dans la documentation l'équivalent de chaque option. Hors de Symfony, `phil/doctrine-behaviours`
se branche à la main.

Le bundle ne contient aucun comportement propre. Il associe la configuration aux écouteurs de la bibliothèque, emploie
l'horloge de Symfony pour l'horodatage et la suppression douce, et donne au port `BlameResolver` de la bibliothèque un
adaptateur sur le stockage de jetons de Symfony Security.

## État

| Partie | État |
|---|---|
| L'horodatage, l'auteur, la suppression douce et les arbres, chacun activé ou désactivé | Implémenté et testé |
| L'auteur selon l'utilisateur authentifié, par identifiant ou par une méthode de la classe d'utilisateur | Implémenté et testé |
| `BlameEraser` comme service public | Implémenté et testé |
| Les filtres ajoutés au gestionnaire d'entités par défaut | Implémenté et testé |
| La désactivation, le propriétaire, la publication, le *slug*, les versions, l'identifiant daté, chacun activé ou désactivé | Implémenté et testé |
| Le propriétaire selon l'utilisateur authentifié, par une méthode qui doit être nommée | Implémenté et testé |
| La machine à états de la publication, déclarée pour `workflow.registry` | Implémenté et testé |
| Le retour à une version passée sous un droit qui lui est propre | Implémenté et testé |
| Les positions dans le groupe que nomme `positionGroup()`, avec le service public `Positions` | Implémenté et testé |
| Les *slugs* uniques dans leur table, ou dans la portée que nomme `slugScope()` | Implémenté et testé |
| Les traductions, une ligne par enregistrement et par locale sans source à compiler, l'index unique de chaque table de traduction et le service public `Translations` | Implémenté et testé |
| L'auteur, le propriétaire et les versions dans les demandes d'accès et d'effacement de `phil/privacy-bundle`, seulement quand il est installé | Implémenté et testé |
| Les champs `#[PersonalData]` laissés de côté dans les versions figées, ou scellés, et la commande qui retire ceux des versions déjà écrites | Implémenté et testé |
| Aucun nom gardé comme auteur pendant que s'exécute un outil d'effacement de `phil/privacy-bundle` | Implémenté et testé |
| La suppression douce après les autres écouteurs d'`onFlush`, pour qu'un index de recherche voie la suppression | Implémenté et testé |
| Les instants de l'horloge de Symfony écrits en UTC, dans les types de colonne que déclare la bibliothèque | Implémenté et testé |
| Les environnements d'exécution en mode *worker* : `TreeListener` et `BlameSuspension` réinitialisés avec `kernel.reset`, tout autre service sans état | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'enregistrement. |
| [Configuration](doc/fr/configuration.md) | Chaque comportement, le filtre, le résolveur d'auteur, les demandes de vie privée, les champs personnels des versions, l'auteur pendant un effacement. |
| [Venir des extensions Gedmo](doc/fr/gedmo.md) | Ce que devient chacune de leurs parties. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle::class => ['all' => true],
```

L'horodatage, l'auteur, la suppression douce, les arbres et la désactivation sont activés ; une entité en adopte un en
implémentant son interface et en utilisant son trait. Les autres sont à une ligne de configuration.

```yaml
phil_doctrine_behaviours:
    owner: { user_method: getUuid }
    publication: true
    slug: true
    versions: true
    dated_identifier: true
```

## Prérequis

| Paquet | Version | |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/doctrine-behaviours` | ^0.1 | requis |
| `doctrine/doctrine-bundle` | ^3.3 | requis |
| `symfony/clock`, `symfony/config`, `symfony/dependency-injection` | ^8.2 | requis |
| `symfony/security-core` | ^8.2 | facultatif : l'auteur et le propriétaire selon l'utilisateur authentifié, le droit de revenir à une version passée |
| `symfony/workflow` | ^8.2 | facultatif : la publication |
| `phil/slug-bundle` | ^0.1 | facultatif : les *slugs* |
| `symfony/uid` | ^8.2 | facultatif : les identifiants datés |
| `phil/privacy-bundle` | ^0.1 | facultatif : l'auteur et le propriétaire dans les demandes de vie privée |

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).

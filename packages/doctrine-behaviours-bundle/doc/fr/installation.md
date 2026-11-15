<!-- phil-dev source-sha256=686265e0469a61b36c217be828e8b9337b4e60979172a73ac42681e7a965c240 -->
# Installation

## Prérequis

| Paquet | Version | Utilisé pour |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/doctrine-behaviours` | ^0.1 | Les comportements |
| `doctrine/doctrine-bundle` | ^3.3 | Le gestionnaire d'entités, l'étiquette d'écouteur d'événements, les filtres, l'association des versions |
| `symfony/clock` | ^8.2 | Le service `clock` des instants |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `symfony/security-core` | ^8.2, facultatif | L'auteur et le propriétaire selon l'utilisateur authentifié, le droit de revenir à une version passée |
| `symfony/workflow` | ^8.2, facultatif | L'état de publication, sous `publication` |
| `phil/slug-bundle` | ^0.1, facultatif | Les générateurs de *slugs*, sous `slug` |
| `symfony/uid` | ^8.2, facultatif | L'UUID v7, sous `dated_identifier` |
| `phil/privacy-bundle` | ^0.1, facultatif | L'auteur et le propriétaire dans les demandes d'accès et d'effacement, sous `privacy` |

`phil/doctrine-behaviours` apporte `phil/locale-dimensions` ^0.1, les locales des traductions, et `doctrine/orm`.

## Enregistrement

```php
// config/bundles.php
return [
    // ...
    Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle::class => ['all' => true],
];
```

Les colonnes des traits viennent avec l'association des entités ; générez la migration comme pour toute autre colonne.

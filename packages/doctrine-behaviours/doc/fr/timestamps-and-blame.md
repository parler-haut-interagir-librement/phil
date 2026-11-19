<!-- phil-dev source-sha256=fc6fed34b708dffdac4bebe380557375f30eab0b0720c60a8e888772d432fd43 -->
# Horodatage et auteur

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Timestampable` | interface | `createdAt()`, `updatedAt()`, `stampCreated()`, `stampUpdated()` |
| `TimestampableEntity` | trait | Les colonnes `created_at` et `updated_at`, `datetime_utc_immutable` |
| `TimestampListener` | écouteur : `prePersist`, `onFlush` | Horodate à l'heure de l'horloge |
| `Contract\Blameable` | interface | `createdBy()`, `updatedBy()`, `blameCreated()`, `blameUpdated()` |
| `BlameableEntity` | trait | Les colonnes `created_by` et `updated_by`, 255 caractères, nullables |
| `BlameListener` | écouteur : `prePersist`, `onFlush` | Inscrit comme auteur celui que nomme le résolveur |
| `Contract\BlameResolver` | interface | `current(): ?string`, qui agit en ce moment |
| `FixedBlame` | `BlameResolver` | Toujours le même, ou personne |
| `BlameEraser` | service | Trouve et remplace le nom d'un utilisateur dans chaque table qui garde l'auteur |
| `BlameSuspension` | service | Une période de travail, un effacement, pendant laquelle `BlameListener` n'écrit aucun nom |

## Quand

| Vidage | Horodatage | Auteur |
|---|---|---|
| une entité persistée | `createdAt`, puis `updatedAt`, mis à maintenant sauf s'ils sont déjà donnés | `createdBy`, puis `updatedBy`, mis à celui qui agit sauf s'ils sont déjà donnés |
| une entité modifiée | `updatedAt` mis à maintenant, sauf si la modification le fixe elle-même | `updatedBy` mis à celui qui agit, sauf si la modification le fixe |
| personne de connu | — | rien n'est écrit |

Ce qu'une entité a reçu reste : une importation qui garde ses dates et ses auteurs d'origine appelle elle-même
`stampCreated()`, `blameCreated()`, `stampUpdated()` ou `blameUpdated()`, et les écouteurs laissent ces valeurs en paix.

L'auteur est un nom de l'utilisateur — ce que donne le résolveur — et non une relation vers une entité utilisateur : la
colonne survit au compte, et aucune entité n'a à connaître la classe de l'utilisateur.

## Vie privée

Ce que donne le résolveur est copié dans chaque ligne qui garde l'auteur, et conservé après la disparition du compte.
Quand c'est une adresse courriel — le `getUserIdentifier()` de Symfony Security en est souvent une —, les colonnes de
l'auteur contiennent un renseignement personnel. Nommez les utilisateurs par un identifiant stable qui n'en est pas un,
comme un UUID, là où le résolveur le permet.

`BlameEraser` répond aux demandes d'accès et d'effacement : `find($who)` compte les lignes qui nomment quelqu'un dans
chaque table qui garde l'auteur, et `replace($who, $replacement)` met à leur place un pseudonyme, ou `null`, dans
`created_by` et `updated_by` de chaque entité `Blameable` — dans la table du parent pour une sous-classe qui en hérite.
Il travaille directement sur les tables : les lignes supprimées en douceur et celles qu'un filtre SQL cache sont
trouvées aussi, et aucun écouteur n'horodate les lignes qu'il change. `tables()` liste chaque table qui garde l'auteur,
trouvée ou non, pour un rapport qui nomme chaque lieu de stockage.

```php
$eraser = new BlameEraser($registry);             // every entity manager, or one: new BlameEraser($manager)
$eraser->find('ada@example.com');                 // ['article' => 12, 'page' => 3]
$eraser->replace('ada@example.com', 'former-member-81');
$eraser->tables();                                // ['article', 'note', 'page']
```

Avec un `ManagerRegistry`, il joint les tables de chaque gestionnaire d'entités : une table que deux gestionnaires
partagent sur une même connexion est comptée et écrite une seule fois, et les tables de chaque connexion sont écrites
dans une transaction de cette connexion, si bien qu'un échec laisse cette connexion comme elle était. Une fois une
connexion écrite, la région du cache de second niveau de chaque classe qu'elle a changée est évincée, et les entités
gérées qui gardent encore l'ancien nom sont détachées, dans chaque gestionnaire de cette connexion : la lecture suivante
obtient le pseudonyme. Ce qu'une telle entité avait en attente est abandonné avec elle ; faites donc un `flush` avant
un effacement.

## L'auteur pendant un effacement

Un effacement qui charge et écrit des entités, un outil d'effacement propre à l'application par exemple, exécute
`BlameListener` comme n'importe quel `flush` : `updated_by` reçoit le nom que donne le résolveur, et avec Symfony
Security c'est l'utilisateur de la requête, la personne qui a demandé l'effacement ou un opérateur, souvent par une
adresse courriel. Deux façons de l'éviter :

- exécuter l'effacement là où personne n'est authentifié, un worker ou une commande de console : le résolveur donne
  `null`, et rien n'est écrit ;
- donner à `BlameListener` une `BlameSuspension`, et exécuter l'effacement dans `during()` : aucun nom n'est écrit, à la
  persistance comme à la modification, jusqu'à la fin de la période la plus extérieure, que son travail ait retourné ou
  levé une exception.

```php
$suspension = new BlameSuspension();
$events->addEventListener([Events::prePersist, Events::onFlush], new BlameListener($resolver, $suspension));

$suspension->during(fn () => $erasure->erase($subject));
```

`BlameSuspension` retient la profondeur des périodes : un worker appelle `reset()` entre deux messages. `BlameEraser`
n'exécute lui-même aucun écouteur. Voir [Écritures en bloc](bulk-writes.md).

<!-- phil-dev source-sha256=6eb1f99f9cf4bb1a548c69be8c0a740cf458e2b1feeeb895dae4dff013f3599a -->
# Horodatage et auteur

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Timestampable` | interface | `createdAt()`, `updatedAt()`, `stampCreated()`, `stampUpdated()` |
| `TimestampableEntity` | trait | Les colonnes `created_at` et `updated_at`, `datetime_immutable` |
| `TimestampListener` | écouteur : `prePersist`, `onFlush` | Horodate à l'heure de l'horloge, en UTC |
| `Contract\Blameable` | interface | `createdBy()`, `updatedBy()`, `blameCreated()`, `blameUpdated()` |
| `BlameableEntity` | trait | Les colonnes `created_by` et `updated_by`, 255 caractères, nullables |
| `BlameListener` | écouteur : `prePersist`, `onFlush` | Inscrit comme auteur celui que nomme le résolveur |
| `Contract\BlameResolver` | interface | `current(): ?string`, qui agit en ce moment |
| `FixedBlame` | `BlameResolver` | Toujours le même, ou personne |
| `BlameEraser` | service | Trouve et remplace le nom d'un utilisateur dans chaque table qui garde l'auteur |

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
$eraser->find('ada@example.com');                 // ['article' => 12, 'page' => 3]
$eraser->replace('ada@example.com', 'former-member-81');
$eraser->tables();                                // ['article', 'note', 'page']
```
